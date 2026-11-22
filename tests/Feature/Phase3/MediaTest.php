<?php

namespace Tests\Feature\Phase3;

use App\Exceptions\InvalidUploadException;
use App\Models\Boat;
use App\Models\Media;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 3.3 — MediaService, the SafeUpload rule and the private file route (PLAN §9, §8.2):
 * real type checks, WebP re-encoding (no EXIF / GPS / hidden payloads), sizes, random names,
 * private documents, deletes, and "PHP disguised as an image".
 */
class MediaTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    private const PDF = "%PDF-1.4\n1 0 obj <<>> endobj\ntrailer <<>>\n%%EOF\n";

    private MediaService $media;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->media = app(MediaService::class);
    }

    /* ------------------------------------------------------------------ images */

    public function test_a_photo_is_re_encoded_to_webp_with_its_sizes_under_a_random_name(): void
    {
        $boat = Boat::factory()->create();
        $admin = User::factory()->admin()->create();

        $photo = $this->media->addImage($boat, 'photos', UploadedFile::fake()->image('My Boat (1).jpg', 2400, 1600), $admin, 'Bow at sunset');

        $this->assertMatchesRegularExpression("#^boats/{$boat->ulid}/photos/".self::UUID.'\.webp$#', $photo->path, 'the visitor\'s file name is never used on disk');
        $this->assertSame(['card' => str_replace('.webp', '-card.webp', $photo->path), 'thumb' => str_replace('.webp', '-thumb.webp', $photo->path)], $photo->variants);
        $this->assertSame([1600, 1067], [$photo->width, $photo->height]);
        $this->assertSame([[1600, 1067], [800, 534], [400, 267]], array_map(fn ($path) => $this->dimensions($path), $photo->paths()), 'each size is made from the one before');
        foreach ($photo->paths() as $path) {
            $this->assertIsWebp(Storage::disk('public')->get($path));
        }

        $this->assertSame(['public', 'image/webp', 'My Boat (1).jpg', 'Bow at sunset', 0], [$photo->disk, $photo->mime, $photo->original_name, $photo->alt, $photo->sort]);
        $this->assertSame(Storage::disk('public')->size($photo->path), $photo->size_bytes);
        $this->assertTrue($photo->model->is($boat));
        $this->assertTrue($photo->uploader->is($admin));

        $second = $this->media->addImage($boat, 'photos', UploadedFile::fake()->image('b.png', 1000, 800));
        $this->assertSame(1, $second->sort, 'a new photo goes last');
        $this->assertSame([$photo->id, $second->id], $boat->photos()->pluck('id')->all());

        $this->assertStringEndsWith('/storage/'.$photo->variants['card'], $photo->url('card'));
        $this->assertSame($photo->url(), $photo->url('no-such-size'));
        $this->assertSame(
            Storage::disk('public')->url($photo->variants['card']).' 800w, '.Storage::disk('public')->url($photo->variants['thumb']).' 400w, '.$photo->url().' 1600w',
            $photo->srcset(),
        );
    }

    public function test_an_image_with_the_wrong_image_extension_is_still_accepted(): void
    {
        // e.g. a WebP saved from a website as "photo.jpg": the real type decides, the stored name is ours.
        $stored = $this->media->storeImage($this->file($this->imageBytes('png'), 'photo.jpg'), 'boats', variants: false);

        $this->assertStringEndsWith('.webp', $stored->path);
        $this->assertSame([300, 200], [$stored->width, $stored->height]);
    }

    public function test_small_images_are_never_enlarged(): void
    {
        $photo = $this->media->addImage(Boat::factory()->create(), 'photos', UploadedFile::fake()->image('small.gif', 700, 500));

        $this->assertSame([700, 500], [$photo->width, $photo->height]);
        $this->assertSame(['thumb'], array_keys($photo->variants), 'no 800 px copy of a 700 px image');
        $this->assertSame($photo->url(), $photo->url('card'), 'a missing size falls back to the main file');
        $this->assertSame('image/webp', $photo->mime, 'a GIF is stored as WebP too');
    }

    public function test_metadata_and_anything_hidden_in_the_file_are_dropped(): void
    {
        $jpeg = $this->jpegWithExtras(
            exifDescription: 'GPS 41.8789 N 87.6359 W SECRET-LOCATION',
            comment: 'SECRET-COMMENT',
            appended: '<?php echo "owned"; ?>',
        );
        $this->assertStringContainsString('SECRET-LOCATION', $jpeg, 'the test file really carries the data');

        $stored = $this->media->storeImage($this->file($jpeg, 'holiday.jpg'), 'harbors', variants: false);
        $bytes = Storage::disk('public')->get($stored->path);

        $this->assertIsWebp($bytes);
        foreach (['SECRET-LOCATION', 'SECRET-COMMENT', '<?php', 'Exif'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $bytes);
        }
        $this->assertSame([], $stored->variants);
        $this->assertSame(['harbors/'.basename($stored->path)], Storage::disk('public')->allFiles());
    }

    #[RequiresPhpExtension('exif')]
    public function test_photos_are_turned_the_right_way_up_before_the_exif_is_dropped(): void
    {
        // A 300 × 200 photo taken with the phone turned: EXIF orientation 6 = "rotate 90° clockwise to view".
        $stored = $this->media->storeImage($this->file($this->jpegWithExtras(orientation: 6), 'portrait.jpg'), 'boats', variants: false);

        $this->assertSame([200, 300], [$stored->width, $stored->height]);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function badImages(): array
    {
        return [
            'PHP code named .jpg' => ['<?php echo "owned"; ?>', 'photo.jpg'],
            'a real image named .php' => ['jpeg', 'shell.php'],
            'a real image named .phtml' => ['jpeg', 'photo.phtml'],
            // (Harmless PHP on purpose: a real web-shell line makes Windows Defender lock the temp file.)
            'PHP behind a GIF header' => ['GIF89a<?php echo "owned"; ?>', 'shell.gif'],
            'SVG (can carry scripts)' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>', 'logo.svg'],
            'HTML named .webp' => ['<html><script>alert(1)</script></html>', 'page.webp'],
            'a PDF where a photo is expected' => [self::PDF, 'boat.pdf'],
            'a PDF named .jpg' => [self::PDF, 'boat.jpg'],
            'an empty file' => ['', 'empty.jpg'],
            'broken JPEG data' => ["\xFF\xD8\xFF\xE0".str_repeat('x', 200), 'broken.jpg'],
        ];
    }

    #[DataProvider('badImages')]
    public function test_files_that_are_not_safe_images_are_refused_and_nothing_is_saved(string $contents, string $name): void
    {
        $contents = $contents === 'jpeg' ? $this->jpegWithExtras() : $contents;

        try {
            $this->media->addImage(Boat::factory()->create(), 'photos', $this->file($contents, $name));
            $this->fail('Expected the file to be refused');
        } catch (InvalidUploadException $e) {
            $this->assertNotSame('', $e->reason());
            $this->assertSame([$e->reason()], $e->errors()['file'], 'a plain 422 on "file" if no form rule ran');
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Media::count());
    }

    public function test_a_decompression_bomb_is_refused_before_it_is_opened(): void
    {
        // 20 000 × 20 000 px declared in a few bytes: decoding it would need about 1.6 GB of memory.
        $png = "\x89PNG\r\n\x1a\n".$this->pngChunk('IHDR', pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0)).$this->pngChunk('IEND', '');

        $this->expectExceptionObject(InvalidUploadException::because('This image is too large: at most 40 megapixels.'));
        try {
            $this->media->storeImage($this->file($png, 'bomb.png'), 'boats');
        } finally {
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_a_failed_database_write_leaves_no_files_behind(): void
    {
        Media::creating(fn () => throw new RuntimeException('Database is down'));

        try {
            $this->media->addImage(Boat::factory()->create(), 'photos', UploadedFile::fake()->image('a.jpg', 900, 600));
            $this->fail('Expected the exception');
        } catch (RuntimeException $e) {
            $this->assertSame('Database is down', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_files_belong_to_saved_records_and_named_collections(): void
    {
        foreach ([fn () => $this->media->directoryFor(new Boat, 'photos'), fn () => $this->media->directoryFor(Boat::factory()->create(), '../../etc')] as $call) {
            try {
                $call();
                $this->fail('Expected a LogicException');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertStringStartsWith('users/', $this->media->directoryFor(User::factory()->create(), 'avatar'), 'by ULID, never the numeric id');
        $this->assertDoesNotMatchRegularExpression('#/\d+/#', $this->media->directoryFor(User::factory()->create(), 'avatar'));
    }

    /* ------------------------------------------------------------------ the form rule */

    public function test_the_safe_upload_rule_for_forms(): void
    {
        $errors = fn (mixed $file, ?SafeUpload $rule = null) => Validator::make(['photo' => $file], ['photo' => [$rule ?? SafeUpload::image()]])->errors()->get('photo');

        $this->assertSame([], $errors(UploadedFile::fake()->image('ok.jpg', 1200, 800)));
        $this->assertSame([], $errors(UploadedFile::fake()->image('portrait.webp', 400, 600)), 'either way round');
        $this->assertSame(['This image is too small: at least 600 × 400 pixels.'], $errors(UploadedFile::fake()->image('tiny.png', 500, 300)));
        $this->assertSame([], $errors(UploadedFile::fake()->image('tiny.png', 500, 300), SafeUpload::image(minWidth: 200, minHeight: 200)));
        $this->assertSame(['This file must be a JPG, PNG, GIF or WEBP image.'], $errors($this->file('<?php echo 1;', 'photo.jpg')));

        $this->assertSame(['This file is larger than 10 MB.'], $errors(UploadedFile::fake()->image('big.jpg', 900, 600)->size(10 * 1024 + 1)));
        $this->assertSame([], $errors(UploadedFile::fake()->image('big.jpg', 900, 600)->size(20 * 1024), SafeUpload::image(maxKb: 25 * 1024)), 'listing photos may be up to 25 MB');

        $this->assertSame(['This file did not upload. Please try again.'], $errors(new UploadedFile($this->tempFile($this->imageBytes('jpg')), 'a.jpg', null, UPLOAD_ERR_INI_SIZE, true)));
        $this->assertSame(['This file did not upload. Please try again.'], $errors('not-a-file.jpg'));

        $this->assertSame([], $errors($this->file(self::PDF, 'coi.pdf'), SafeUpload::document()));
        $this->assertSame([], $errors(UploadedFile::fake()->image('coi.jpg', 100, 100), SafeUpload::document()), 'a photo of a document has no minimum size');
        $this->assertSame(['This file must be a PDF or a JPG, PNG, GIF or WEBP image.'], $errors($this->file('%PDF-1.4 but named .txt', 'coi.txt'), SafeUpload::document()));
    }

    /* ------------------------------------------------------------------ private documents */

    public function test_documents_stay_on_the_private_disk(): void
    {
        $owner = User::factory()->owner()->create();
        $boat = Boat::factory()->ownedBy($owner)->create();

        $pdf = $this->media->addDocument($boat, 'coi', $this->file(self::PDF, 'Insurance COI 2026.pdf'), $owner);
        $this->assertSame(['local', 'application/pdf', 'Insurance COI 2026.pdf'], [$pdf->disk, $pdf->mime, $pdf->original_name]);
        $this->assertMatchesRegularExpression("#^boats/{$boat->ulid}/coi/".self::UUID.'\.pdf$#', $pdf->path);
        $this->assertSame(self::PDF, Storage::disk('local')->get($pdf->path), 'a PDF is kept byte for byte');
        $this->assertNull($pdf->url(), 'no public URL');
        $this->assertNull($pdf->srcset());

        $scan = $this->media->addDocument($boat, 'coi', UploadedFile::fake()->image('scan.png', 900, 1200), $owner);
        $this->assertSame(['local', 'image/webp', null], [$scan->disk, $scan->mime, $scan->variants]);
        $this->assertSame(1, $scan->sort);

        $this->assertSame([], Storage::disk('public')->allFiles(), 'nothing private on the public disk');

        $this->expectException(InvalidUploadException::class);
        $this->media->addDocument($boat, 'coi', $this->file('just text', 'fake.pdf'), $owner);
    }

    /* ------------------------------------------------------------------ deleting */

    public function test_deleting_removes_the_row_and_its_files_once_the_transaction_commits(): void
    {
        $boat = Boat::factory()->create();
        $first = $this->media->addImage($boat, 'photos', UploadedFile::fake()->image('a.jpg', 1700, 1000));
        $second = $this->media->addImage($boat, 'photos', UploadedFile::fake()->image('b.jpg', 1700, 1000));
        $this->media->addDocument($boat, 'coi', $this->file(self::PDF, 'coi.pdf'));

        // Rolled back: the row comes back, so its files must stay.
        try {
            DB::transaction(function () use ($first) {
                $this->media->delete($first);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertModelExists($first);
        Storage::disk('public')->assertExists($first->paths());

        $this->media->delete($first); // this model object thinks it is gone: nothing happens to the row or the files
        Storage::disk('public')->assertExists($first->paths());

        $first = Media::query()->findOrFail($first->id);
        $this->media->delete($first);
        $this->assertModelMissing($first);
        Storage::disk('public')->assertMissing($first->paths());
        Storage::disk('public')->assertExists($second->paths());

        $this->assertSame(2, $this->media->deleteAllFor($boat), 'the second photo and the document');
        $this->assertSame(0, Media::count());
        $this->assertSame([], [...Storage::disk('public')->allFiles(), ...Storage::disk('local')->allFiles()]);

        $this->media->deleteFiles('public', [null, '']); // nothing to do, no error
    }

    /* ------------------------------------------------------------------ the private file route */

    public function test_a_private_file_opens_only_for_the_admin_and_its_uploader(): void
    {
        $owner = User::factory()->owner()->create();
        $boat = Boat::factory()->ownedBy($owner)->create();
        $coi = $this->media->addDocument($boat, 'coi', $this->file(self::PDF, 'Insurance COI 2026.pdf'), $owner);
        $url = route('files.show', $coi->uuid);

        $this->get($url)->assertRedirect(route('login'));
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->actingAs(User::factory()->owner()->create())->get($url)->assertNotFound();
        $this->actingAs(User::factory()->owner()->suspended()->create())->get($url)->assertRedirect(route('login'));

        foreach ([$owner, User::factory()->admin()->create()] as $reader) {
            $response = $this->actingAs($reader)->get($url)->assertOk()
                ->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'attachment; filename=insurance-coi-2026.pdf')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertSame(self::PDF, $response->streamedContent());
        }

        $scan = $this->media->addDocument($boat, 'coi', UploadedFile::fake()->image('scan.jpg', 900, 1200), $owner);
        $this->actingAs($owner)->get(route('files.show', $scan->uuid))->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Content-Disposition', 'inline; filename=scan.webp');
    }

    public function test_public_photos_unknown_files_and_missing_files_are_404(): void
    {
        $admin = User::factory()->admin()->create();
        $boat = Boat::factory()->create();
        $photo = $this->media->addImage($boat, 'photos', UploadedFile::fake()->image('a.jpg', 900, 600), $admin);
        $coi = $this->media->addDocument($boat, 'coi', $this->file(self::PDF, 'coi.pdf'), $admin);
        Storage::disk('local')->delete($coi->path);

        $this->actingAs($admin);
        $this->get(route('files.show', $photo->uuid))->assertNotFound(); // it has its own public URL
        $this->get(route('files.show', $coi->uuid))->assertNotFound();
        $this->get('/files/'.fake()->uuid())->assertNotFound();
        $this->get('/files/'.$coi->id)->assertNotFound();
        $this->getJson('/files/'.fake()->uuid())->assertNotFound()->assertExactJson(['message' => 'Not found.']);
    }

    public function test_opening_files_is_rate_limited(): void
    {
        $owner = User::factory()->owner()->create();
        $coi = $this->media->addDocument(Boat::factory()->ownedBy($owner)->create(), 'coi', $this->file(self::PDF, 'coi.pdf'), $owner);
        $this->actingAs($owner);

        for ($i = 0; $i < 60; $i++) {
            $this->get(route('files.show', $coi->uuid))->assertOk();
        }
        $this->get(route('files.show', $coi->uuid))->assertTooManyRequests();
    }

    public function test_the_public_storage_folder_never_serves_or_runs_scripts(): void
    {
        $rules = (string) file_get_contents(storage_path('app/public/.htaccess'));

        $this->assertStringContainsString('Require all denied', $rules);
        $this->assertMatchesRegularExpression('/php\\\\d\?\|phtml\|phar/', $rules);
        $this->assertStringContainsString('!.htaccess', (string) file_get_contents(storage_path('app/public/.gitignore')), 'deployed with the code');
    }

    /* ------------------------------------------------------------------ helpers */

    private function assertIsWebp(string $bytes): void
    {
        $this->assertSame(['RIFF', 'WEBP'], [substr($bytes, 0, 4), substr($bytes, 8, 4)]);
    }

    /** @return array{0: int, 1: int} */
    private function dimensions(string $path): array
    {
        $size = getimagesize(Storage::disk('public')->path($path));

        return [$size[0], $size[1]];
    }

    private function file(string $contents, string $name): UploadedFile
    {
        return new UploadedFile($this->tempFile($contents), $name, null, null, true);
    }

    private function tempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cyb-upload-');
        file_put_contents($path, $contents);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    private function imageBytes(string $type, int $width = 300, int $height = 200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 120, 40));
        ob_start();
        $type === 'png' ? imagepng($image) : imagejpeg($image);

        return (string) ob_get_clean();
    }

    /**
     * A 300 × 200 JPEG with an EXIF block (orientation + a description, the way GPS data rides along),
     * a comment and bytes after the image data.
     */
    private function jpegWithExtras(int $orientation = 1, string $exifDescription = '', string $comment = '', string $appended = ''): string
    {
        $jpeg = $this->imageBytes('jpg');

        // EXIF (little-endian TIFF): IFD0 with Orientation (SHORT) and ImageDescription (ASCII, stored after the IFD).
        $description = $exifDescription."\0";
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
            .pack('vvVV', 0x010E, 2, strlen($description), 8 + 2 + 2 * 12 + 4)
            .pack('V', 0)
            .$description;
        $exif = "\xFF\xE1".pack('n', strlen("Exif\0\0".$tiff) + 2)."Exif\0\0".$tiff;
        $com = $comment === '' ? '' : "\xFF\xFE".pack('n', strlen($comment) + 2).$comment;

        // After the JFIF header GD writes (SOI + APP0).
        $insertAt = 4 + unpack('n', substr($jpeg, 4, 2))[1];

        return substr($jpeg, 0, $insertAt).$exif.$com.substr($jpeg, $insertAt).$appended;
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}

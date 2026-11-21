@extends('errors.layout')

@section('title', 'Too many requests')
@section('code', '429')
@section('heading', 'Slow down a little')
@section('message', "We received too many requests from your connection. Please wait a minute and try again.")

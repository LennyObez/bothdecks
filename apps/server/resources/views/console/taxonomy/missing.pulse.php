@extends('console.layout')

@section('title')Not found@endsection

@section('content')
<h1 class="console__title">Not found</h1>
<section class="card">
  <p>No occupation with identifier {{ $id }} in the current version. <a href="/console/taxonomy/occupations?locale={{ $locale }}">Search for one.</a></p>
</section>
@endsection

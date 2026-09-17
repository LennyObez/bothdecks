@extends('console.layout')

@section('title')Occupations@endsection

@section('content')
<h1 class="console__title">Occupations</h1>

<form class="search" method="get" action="/console/taxonomy/occupations" role="search">
  <label for="q">Find an occupation by any of its labels in {{ $locale }}</label>
  <div class="search__row">
    <input id="q" name="q" type="search" value="{{ $query }}" autocomplete="off">
    <input type="hidden" name="locale" value="{{ $locale }}">
    <button type="submit">Search</button>
  </div>
</form>

@if($query !== '')
<section class="card" aria-labelledby="results">
  <h2 id="results">{{ count($results) }} result(s)@if(count($results) === $limit), showing the first {{ $limit }}@endif</h2>
  @if(count($results) === 0)
  <p>Nothing carries that text in {{ $locale }}. The resolver may still find it: <a href="/console/taxonomy/resolve?q={{ rawurlencode($query) }}&amp;locale={{ $locale }}">run the cascade on it</a>.</p>
  @else
  <table class="table">
    <thead><tr><th scope="col">Code</th><th scope="col">Label</th></tr></thead>
    <tbody>
      @foreach($results as $result)
      <tr>
        <td><code>{{ $result['code'] ?? '' }}</code></td>
        <td><a href="/console/taxonomy/occupations/{{ $result['id'] }}?locale={{ $locale }}">{{ $result['label'] }}</a></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</section>
@endif
@endsection

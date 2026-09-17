@extends('console.layout')

@section('title')Resolve@endsection

@section('content')
<h1 class="console__title">Resolve</h1>
<p>Runs the cascade the product runs, exact, then approximate, then vector, then ask, and shows the path. Every run here is logged like a real one.</p>

<form class="search" method="get" action="/console/taxonomy/resolve" role="search">
  <label for="q">Text in {{ $locale }}</label>
  <div class="search__row">
    <input id="q" name="q" type="search" value="{{ $query }}" autocomplete="off">
    <select name="kind" aria-label="Kind">
      <option value="occupation" @if($kind === 'occupation') selected @endif>occupation</option>
      <option value="skill" @if($kind === 'skill') selected @endif>skill</option>
    </select>
    <input type="hidden" name="locale" value="{{ $locale }}">
    <button type="submit">Resolve</button>
  </div>
</form>

@if($resolution !== null)
<section class="card" aria-labelledby="outcome">
  @if($resolution->resolved() !== null)
  <h2 id="outcome">Resolved by the <em>{{ $resolution->path->value }}</em> step</h2>
  <p class="outcome">
    <code class="code">{{ $resolution->resolved()->code ?? $resolution->resolved()->uri }}</code>
    <strong>{{ $resolution->resolved()->label }}</strong>
    <span class="num">score {{ number_format($resolution->resolved()->score, 3) }}</span>
  </p>
  @else
  <h2 id="outcome">No confident answer: the user would be asked</h2>
  @if(count($resolution->candidates) === 0)
  <p>Nothing came close.</p>
  @else
  <table class="table">
    <thead><tr><th scope="col">Code</th><th scope="col">Candidate</th><th scope="col" class="num">Score</th></tr></thead>
    <tbody>
      @foreach($resolution->candidates as $candidate)
      <tr>
        <td><code>{{ $candidate->code ?? '' }}</code></td>
        <td>{{ $candidate->label }}</td>
        <td class="num">{{ number_format($candidate->score, 3) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
  @endif
  <p class="console__meta">Version {{ $resolution->versionId }}, locale {{ $resolution->locale }}.</p>
</section>
@endif
@endsection

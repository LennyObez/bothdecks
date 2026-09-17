@extends('console.layout')

@section('title')Taxonomy@endsection

@section('content')
<h1 class="console__title">Taxonomy</h1>

<section class="card" aria-labelledby="version">
  <h2 id="version">Current version</h2>
  <dl class="facts">
    <div><dt>Source</dt><dd>{{ $report->version->source }} {{ $report->version->sourceVersion }}</dd></div>
    <div><dt>Version</dt><dd>{{ $report->version->id }}</dd></div>
    <div><dt>Snapshot</dt><dd><code>{{ substr($report->version->digest, 0, 12) }}</code>, taken {{ $report->version->snapshotTakenAt }}</dd></div>
    <div><dt>Imported</dt><dd>{{ $report->version->importedAt }}</dd></div>
    <div><dt>Licence</dt><dd>{{ $report->version->licence }}. {{ $report->version->attribution }}.</dd></div>
  </dl>
  <table class="table table--compact">
    <caption>Concepts by kind</caption>
    <thead><tr><th scope="col">Kind</th><th scope="col" class="num">Count</th></tr></thead>
    <tbody>
      @foreach($report->version->concepts as $kind => $count)
      <tr><th scope="row">{{ $kind }}</th><td class="num">{{ $count }}</td></tr>
      @endforeach
    </tbody>
  </table>
</section>

@if($report->migration !== null)
<section class="card" aria-labelledby="migration">
  <h2 id="migration">Since version {{ $report->migration->from }}</h2>
  <table class="table table--compact">
    <caption>Concepts that appeared or disappeared, by kind</caption>
    <thead><tr><th scope="col">Kind</th><th scope="col" class="num">Added</th><th scope="col" class="num">Removed</th></tr></thead>
    <tbody>
      @foreach($report->migration->added as $kind => $added)
      <tr><th scope="row">{{ $kind }}</th><td class="num">{{ count($added) }}</td><td class="num">{{ count($report->migration->removed[$kind] ?? []) }}</td></tr>
      @endforeach
    </tbody>
  </table>
  <p>{{ $report->migration->preferredLabelsChanged }} preferred labels changed in {{ $fallback }}; skill relations: {{ $report->migration->skillRelationsAdded }} added, {{ $report->migration->skillRelationsRemoved }} removed.</p>
</section>
@else
<section class="card" aria-labelledby="migration">
  <h2 id="migration">Migration</h2>
  <p>No previous version to compare with.</p>
</section>
@endif

<section class="card" aria-labelledby="coverage">
  <h2 id="coverage">Coverage by language</h2>
  <table class="table table--compact">
    <caption>Share of concepts with a preferred label and with a description, and count of alternative labels</caption>
    <thead>
      <tr>
        <th scope="col">Language</th>
        <th scope="col" class="num">Occupations labelled</th>
        <th scope="col" class="num">described</th>
        <th scope="col" class="num">alternatives</th>
        <th scope="col" class="num">Skills labelled</th>
        <th scope="col" class="num">described</th>
        <th scope="col" class="num">alternatives</th>
      </tr>
    </thead>
    <tbody>
      @foreach($locales as $code)
      <tr>
        <th scope="row">{{ $code }}</th>
        <td class="num">{{ number_format(100 * $report->measures->coverage['occupation'][$code]->labelledShare(), 1) }}%</td>
        <td class="num">{{ number_format(100 * $report->measures->coverage['occupation'][$code]->describedShare(), 1) }}%</td>
        <td class="num">{{ $report->measures->coverage['occupation'][$code]->alternativeLabels }}</td>
        <td class="num">{{ number_format(100 * $report->measures->coverage['skill'][$code]->labelledShare(), 1) }}%</td>
        <td class="num">{{ number_format(100 * $report->measures->coverage['skill'][$code]->describedShare(), 1) }}%</td>
        <td class="num">{{ $report->measures->coverage['skill'][$code]->alternativeLabels }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
</section>

<section class="card" aria-labelledby="signals">
  <h2 id="signals">Vectors, bubbles and resolutions</h2>
  <dl class="facts">
    <div><dt>Vectors ({{ $report->measures->embeddingModel }})</dt><dd>{{ $report->measures->vectors['occupation'] ?? 0 }} occupations, {{ $report->measures->vectors['skill'] ?? 0 }} skills</dd></div>
    <div><dt>Bubbles</dt><dd>{{ $report->measures->bubbles }}, of which {{ $report->measures->emptyBubbles }} empty; {{ $report->measures->meanMembers }} members on average</dd></div>
    <div><dt>Corrections</dt><dd>{{ $report->measures->overridesRecorded }} recorded; {{ $report->measures->membersAddedByAPerson }} members added by a person</dd></div>
    @if($report->measures->bubbleParameters !== null)
    <div><dt>Computed with</dt><dd><code>{{ $report->measures->bubbleParameters }}</code></dd></div>
    @endif
    <div><dt>Resolutions logged</dt><dd>{{ $report->measures->resolutions() }}
      @foreach($report->measures->resolutionsByPath as $path => $count) · {{ $path }} {{ $count }}@endforeach
    </dd></div>
    <div><dt>Share asking the user</dt><dd>@if($report->measures->shareAsking() === null)no resolutions yet@else{{ number_format(100 * $report->measures->shareAsking(), 1) }}%@endif</dd></div>
  </dl>
</section>
@endsection

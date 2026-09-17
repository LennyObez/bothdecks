@extends('console.layout')

@section('title'){{ $occupation['label'] }}@endsection

@section('content')
<p class="console__crumbs"><a href="/console/taxonomy/occupations?locale={{ $locale }}">Occupations</a></p>
<h1 class="console__title"><code class="code">{{ $occupation['code'] }}</code> {{ $occupation['label'] }}</h1>

<section class="card" aria-labelledby="about">
  <h2 id="about">About</h2>
  <dl class="facts">
    <div><dt>Group</dt><dd><code>{{ $occupation['isco_code'] }}</code> {{ $occupation['isco_label'] }}</dd></div>
    <div><dt>Status</dt><dd>{{ $occupation['status'] }}</dd></div>
    <div><dt>Identifier</dt><dd><code>{{ $occupation['uri'] }}</code></dd></div>
    <div><dt>Provenance</dt><dd><a href="{{ $occupation['source_uri'] }}" rel="external">{{ $occupation['source_uri'] }}</a>, {{ $occupation['licence'] }}</dd></div>
  </dl>
  @if($occupation['description'] !== null)
  <p class="prose">{{ $occupation['description'] }}</p>
  @endif
  @if(count($alternatives) > 0)
  <p><strong>Also called</strong>: {{ implode(' · ', $alternatives) }}</p>
  @endif
</section>

<section class="card" aria-labelledby="bubble">
  <h2 id="bubble">Bubble</h2>
  <p>Occupations whose offers someone in this occupation would see, heaviest first. Each weight combines the four signals; a member added by a person says so.</p>
  @if(count($neighbours) === 0)
  <p>No bubble has been computed for this occupation, or every candidate fell under the floor.</p>
  @else
  <table class="table">
    <thead>
      <tr>
        <th scope="col">Code</th>
        <th scope="col">Neighbour</th>
        <th scope="col" class="num">Weight</th>
        <th scope="col" class="num">Hierarchy</th>
        <th scope="col" class="num">Skills</th>
        <th scope="col" class="num">Vector</th>
        <th scope="col" class="num">Mobility</th>
        <th scope="col">Origin</th>
      </tr>
    </thead>
    <tbody>
      @foreach($neighbours as $neighbour)
      <tr>
        <td><code>{{ $neighbour->code ?? '' }}</code></td>
        <td><a href="/console/taxonomy/occupations/{{ $neighbour->conceptId }}?locale={{ $locale }}">{{ $neighbour->label }}</a></td>
        <td class="num"><strong>{{ number_format($neighbour->weight, 2) }}</strong></td>
        <td class="num">{{ number_format($neighbour->hierarchy, 2) }}</td>
        <td class="num">{{ number_format($neighbour->skills, 2) }}</td>
        <td class="num">{{ number_format($neighbour->vector, 2) }}</td>
        <td class="num">{{ number_format($neighbour->mobility, 2) }}</td>
        <td>@if($neighbour->origin->value === 'added')<span class="badge badge--person">added by a person</span>@else computed @endif</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</section>

<section class="card" aria-labelledby="corrections">
  <h2 id="corrections">Corrections</h2>
  @if(count($overrides) === 0)
  <p>None recorded for this occupation.</p>
  @else
  <table class="table">
    <thead><tr><th scope="col">Neighbour</th><th scope="col">Action</th><th scope="col" class="num">Weight</th><th scope="col">Reason</th><th scope="col">By</th><th scope="col">When</th></tr></thead>
    <tbody>
      @foreach($overrides as $override)
      <tr>
        <td>@if($override['label'] !== null)<code>{{ $override['code'] ?? '' }}</code> {{ $override['label'] }}@else<code>{{ $override['neighbour_uri'] }}</code> (not in this version)@endif</td>
        <td>{{ $override['action'] }}</td>
        <td class="num">@if($override['weight'] !== null){{ number_format($override['weight'], 2) }}@endif</td>
        <td>{{ $override['reason'] }}</td>
        <td>{{ $override['author'] }}</td>
        <td>{{ $override['created_at'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
  <p>To add or exclude a neighbour, at the console:</p>
  <pre class="command"><code>php bin/bothdecks taxonomy:bubble:override --occupation={{ $occupation['code'] }} --neighbour=&lt;code&gt; --action=include --reason="..." --author="..."</code></pre>
</section>

<section class="card" aria-labelledby="skills">
  <h2 id="skills">Skills</h2>
  <ul class="skills">
    @foreach($skills as $skill)
    <li class="skills__item skills__item--{{ $skill['relation'] }}">{{ $skill['label'] }} <span class="skills__relation">{{ $skill['relation'] }}</span></li>
    @endforeach
  </ul>
</section>
@endsection

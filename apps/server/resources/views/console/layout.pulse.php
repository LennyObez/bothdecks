<!doctype html>
<html lang="{{ $locale }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title') · {{ $product }}</title>
  <link rel="stylesheet" href="/assets/design/generated/colors.css">
  <link rel="stylesheet" href="/assets/design/generated/foundations.css">
  <link rel="stylesheet" href="/assets/console.css">
  {!! $pulsarSignature !!}
</head>
<body class="console">
  <a class="console__skip" href="#main">Skip to content</a>
  <header class="console__bar">
    <p class="console__brand">{{ $product }} <span class="console__area">console</span></p>
    <nav class="console__nav" aria-label="Console">
      <a href="/console/taxonomy?locale={{ $locale }}">Taxonomy</a>
      <a href="/console/taxonomy/occupations?locale={{ $locale }}">Occupations</a>
      <a href="/console/taxonomy/resolve?locale={{ $locale }}">Resolve</a>
    </nav>
    <form class="console__locale" method="get" action="">
      <label for="locale">Language</label>
      <select id="locale" name="locale">
        @foreach($locales as $candidate)
        <option value="{{ $candidate }}" @if($candidate === $locale) selected @endif>{{ $candidate }}</option>
        @endforeach
      </select>
      <button type="submit">Read in this language</button>
    </form>
  </header>
  <main id="main" class="console__main">
    @yield('content')
  </main>
  <footer class="console__foot">
    <p>Inspection only. Corrections are recorded at the console with a reason and an author.</p>
  </footer>
</body>
</html>

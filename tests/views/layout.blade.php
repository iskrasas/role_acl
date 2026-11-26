<!DOCTYPE html>
<html>
<body>
<aside>
    @foreach (($asideItems ?? []) as $item)
        <a href="{{ $item['url'] }}" class="{{ $item['active'] ? 'active' : '' }}"><i class="fas {{ $item['icon'] }}"></i>{{ $item['label'] }}</a>
    @endforeach
</aside>
<ol>@yield('breadcrumbs')</ol>
<h1>@yield('title')</h1>
@yield('content')
</body>
</html>

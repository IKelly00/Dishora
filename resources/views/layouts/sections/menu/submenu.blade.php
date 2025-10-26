@php
    use Illuminate\Support\Facades\Route;
@endphp

<ul class="menu-sub">
    @if (isset($menu))
        @foreach ($menu as $submenu)
            {{-- set active menu class --}}
            @php
                $activeClass = null; // default
                $active = 'active open'; // class for active
                $currentRouteName = Route::currentRouteName(); // current route

                // if current route = slug
                if ($currentRouteName === $submenu->slug) {
                    $activeClass = 'active';
                }
                // if has submenu
                elseif (isset($submenu->submenu)) {
                    // check if slug is array
                    if (gettype($submenu->slug) === 'array') {
                        foreach ($submenu->slug as $slug) {
                            // match route name with slug
                            if (str_contains($currentRouteName, $slug) and strpos($currentRouteName, $slug) === 0) {
                                $activeClass = $active;
                            }
                        }
                    } else {
                        // single slug check
                        if (
                            str_contains($currentRouteName, $submenu->slug) and
                            strpos($currentRouteName, $submenu->slug) === 0
                        ) {
                            $activeClass = $active;
                        }
                    }
                }
            @endphp

            {{-- menu item --}}
            <li class="menu-item {{ $activeClass }}">
                <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}"
                    class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}"
                    @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif>

                    {{-- icon --}}
                    @if (isset($submenu->icon))
                        <i class="{{ $submenu->icon }}"></i>
                    @endif

                    {{-- name --}}
                    <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>

                    {{-- badge --}}
                    @isset($submenu->badge)
                        <div class="badge bg-{{ $submenu->badge[0] }} rounded-pill ms-auto">
                            {{ $submenu->badge[1] }}
                        </div>
                    @endisset
                </a>

                {{-- nested submenu --}}
                @if (isset($submenu->submenu))
                    @include('layouts.sections.menu.submenu', ['menu' => $submenu->submenu])
                @endif
            </li>
        @endforeach
    @endif
</ul>

@php use App\Support\Persian; @endphp
<span class="plate">
    <span class="pl-flag"><i></i><i></i><i></i></span>
    <span class="pl-main">
        <span>{{ Persian::digits($v->plate_two) }}</span>
        <span class="pl-let">{{ $v->plate_letter }}</span>
        <span>{{ Persian::digits($v->plate_three) }}</span>
    </span>
    <span class="pl-prov"><b>{{ Persian::digits($v->plate_province) }}</b><small>ایران</small></span>
</span>

@props(['columns', 'caption'])
<div class="table-scroll {{ count($columns) > 4 ? 'table-wide' : 'table-compact' }}" tabindex="0" role="region" aria-label="{{ $caption }}"><table><caption class="sr-only">{{ $caption }}</caption><thead><tr>@foreach($columns as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead><tbody>{{ $slot }}</tbody></table></div>

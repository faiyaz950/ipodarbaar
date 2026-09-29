@props(['name', 'label', 'value' => '', 'hint' => null, 'long' => false])
{{-- long: the blog editor, with section headings (H2/H3), IPO cards and a bigger limit. --}}
<div class="af">
    <span class="af-label" id="{{ $name }}-label">{{ $label }}</span>
    <div class="rte{{ $long ? ' rte-long' : '' }}" data-rte>
        <div class="rte-bar" role="toolbar" aria-label="Formatting">
            <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
            <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
            @if ($long)
                <button type="button" data-cmd="formatBlock" data-arg="h2" title="Section heading">H2</button>
                <button type="button" data-cmd="formatBlock" data-arg="h3" title="Sub-heading">H3</button>
                <button type="button" data-cmd="formatBlock" data-arg="blockquote" title="Quote">“ ”</button>
            @else
                <button type="button" data-cmd="formatBlock" data-arg="h3" title="Heading">H</button>
            @endif
            <button type="button" data-cmd="formatBlock" data-arg="p" title="Normal text">¶</button>
            <span class="rte-sep"></span>
            <button type="button" data-cmd="insertUnorderedList" title="Bullet points">• List</button>
            <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
            <button type="button" data-cmd="createLink" title="Add link">Link</button>
            <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
            @if ($long)
                <span class="rte-sep"></span>
                <button type="button" data-rte-ipo title="Insert a live IPO card">+ IPO card</button>
            @endif
            <span class="rte-sep"></span>
            <button type="button" data-rte-source title="Edit the raw HTML">&lt;/&gt;</button>
        </div>
        <div class="rte-body prose" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="{{ $name }}-label" data-rte-body></div>
        <textarea class="input rte-source" name="{{ $name }}" rows="{{ $long ? 24 : 12 }}" maxlength="{{ $long ? 150000 : 60000 }}" data-rte-input hidden>{{ old($name, $value) }}</textarea>
    </div>
    @if ($hint)<span class="af-hint">{{ $hint }}</span>@endif
    @error($name)<span class="af-error">{{ $message }}</span>@enderror
</div>

@props(['name', 'label', 'value' => '', 'hint' => null])
<div class="af">
    <span class="af-label" id="{{ $name }}-label">{{ $label }}</span>
    <div class="rte" data-rte>
        <div class="rte-bar" role="toolbar" aria-label="Formatting">
            <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
            <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
            <button type="button" data-cmd="formatBlock" data-arg="h3" title="Heading">H</button>
            <button type="button" data-cmd="formatBlock" data-arg="p" title="Normal text">¶</button>
            <span class="rte-sep"></span>
            <button type="button" data-cmd="insertUnorderedList" title="Bullet points">• List</button>
            <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
            <button type="button" data-cmd="createLink" title="Add link">Link</button>
            <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
            <span class="rte-sep"></span>
            <button type="button" data-rte-source title="Edit the raw HTML">&lt;/&gt;</button>
        </div>
        <div class="rte-body prose" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="{{ $name }}-label" data-rte-body></div>
        <textarea class="input rte-source" name="{{ $name }}" rows="12" maxlength="60000" data-rte-input hidden>{{ old($name, $value) }}</textarea>
    </div>
    @if ($hint)<span class="af-hint">{{ $hint }}</span>@endif
    @error($name)<span class="af-error">{{ $message }}</span>@enderror
</div>

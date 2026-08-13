<div x-data="{ isEditor: false }" class="{{ $classList }}" id="{{ $groupID }}" data-group-handle="{{ $groupHandle }}">
    @if(!empty($groupTitle) || !empty($groupDescription))
        <div class="meros-form-group-header">
            @if(!empty($groupTitle))
                <h3>{{ $groupTitle }}</h3>
            @endif

            @if(!empty($groupDescription))
                <p>{{ $groupDescription }}</p>
            @endif
        </div>
    @endif

    @foreach(($groupRows) as $groupRow)
        <div class="meros-form-row">
            @foreach($groupRow->getFields() ?? [] as $groupField)
                <div class="meros-form-field">
                    @if(($groupField->handle ?? null) === 'repeater')
                        {!! $groupField->render() !!}
                    @else
                        {!! $groupField->render() !!}
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
<div class="meros-form-group" @if(!empty($group['id'])) id="{{ $group['id'] }}" @endif>
    @if(!empty($group['title']) || !empty($group['description']))
        <div class="meros-form-group-header">
            @if(!empty($group['title']))
                <h3>{{ $group['title'] }}</h3>
            @endif

            @if(!empty($group['description']))
                <p>{!! $this->renderQuillContent($group['description']) !!}</p>
            @endif
        </div>
    @endif

    @foreach(($group['rows'] ?? []) as $groupRow)
        <div class="meros-form-row">
            @foreach($groupRow['fields'] ?? [] as $groupField)
                <div class="meros-form-field">
                    @if(($groupField->handle ?? null) === 'repeater')
                        {!! $groupField->render(true, true) !!}
                    @else
                        {!! $groupField->render() !!}
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach
</div>
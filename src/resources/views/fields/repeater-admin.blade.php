@php
    $showsMoveColumn = $field->allowsReorder();
    $showsActionsColumn = $field->allowsConfigure() || $field->allowsRemove();
    $configurationCallback = $field->getConfigurationCallback();
@endphp
<div class="meros-repeater-field {!! $field->classList() !!}">
    <div class="meros-repeater-rows">
        <table class="widefat striped meros-repeater-table">
            <thead>
                <tr>
                    @if ($showsMoveColumn)
                        <th class="meros-repeater-column meros-col-handle"></th>
                    @endif
                    @foreach ($field->getFieldLabels() as $label)
                        <th scope="col" class="meros-repeater-column meros-col-{{ strtolower($label) }}">{{ $label }}</th>
                    @endforeach
                    @if ($showsActionsColumn)
                        <th class="meros-repeater-column meros-col-actions"></th>
                    @endif
                </tr>
            </thead>
            
            <tbody>
                @foreach ($rows as $row)
                    <tr draggable="false" class="meros-draggable-row">
                        @if ($showsMoveColumn)
                            <td class="meros-repeater-column meros-col-handle meros-drag-handle">☰</td>
                        @endif
                        @foreach ($field->getFieldNames() as $name)
                            <td class="meros-repeater-column meros-col-{{ $name }}">
                                @php
                                    $subField = $row[$name] ?? null;           
                                @endphp
                                @if($subField)
                                    {{ $subField->render(false, false) }}
                                @endif
                            </td>
                        @endforeach
                        @if ($showsActionsColumn)
                            <td class="meros-repeater-column meros-repeater-actions">
                                @if ($field->allowsConfigure())
                                    <button type="button" class="button meros-configure-row" onclick="window['{{ $configurationCallback }}']?.(this)">
                                        Configure
                                    </button>
                                @endif
                                @if ($field->allowsRemove())
                                    <button type="button" class="button meros-remove-row">
                                        Remove
                                    </button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($field->allowsAdd())
        <div class="meros-repeater-footer">
            <button type="button" class="button button-secondary meros-add-row" style="margin-top: 10px;">
                Add Row
            </button>
        </div>
    @endif
</div>
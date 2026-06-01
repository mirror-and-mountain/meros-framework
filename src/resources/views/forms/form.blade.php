<div class="meros-form-container">
	<div class="meros-form-header">
		@if($showTitle)
			<h1 class="meros-form-title">{{ $formTitle }}</h1>
		@endif
		@if($showDescription && !empty($formDescription))
			<div class="meros-form-description">{!! $formDescription !!}</div>
		@endif
	</div>
	<form class="meros-form">
		@foreach(($formRows ?? []) as $formRow)
			@if(($formRow['type'] ?? null) === 'group')
				@php
					$group = $formRow['group'] ?? [];
				@endphp

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
							@foreach($groupRow['fields'] ?? [] as $fieldItem)
								@php
									$field = is_array($fieldItem) ? ($fieldItem['field'] ?? null) : $fieldItem;
								@endphp
								@if($field)
									<div class="meros-form-field">
										@if(($field->handle ?? null) === 'repeater')
											{!! $field->render(true, true) !!}
										@else
											{!! $field->render() !!}
										@endif
									</div>
								@endif
							@endforeach
						</div>
					@endforeach
				</div>
				
			@else
				<div class="meros-form-row">
					@foreach(($formRow['fields'] ?? []) as $fieldItem)
						@php
							$field = is_array($fieldItem) ? ($fieldItem['field'] ?? null) : $fieldItem;
						@endphp
						@if($field)
							<div class="meros-form-field">
								@if(($field->handle ?? null) === 'repeater')
									{!! $field->render(true, true) !!}
								@else
									{!! $field->render() !!}
								@endif
							</div>
						@endif
					@endforeach
				</div>
			@endif
		@endforeach
	</form>
</div>
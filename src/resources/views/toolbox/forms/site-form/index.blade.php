<div class="meros-site-form-container">
	<div class="meros-site-form-header">
		<h1 class="meros-site-form-title">{{ $formTitle }}</h1>
		@if(!empty($formDescription))
			<div class="meros-site-form-description">{!! $formDescription !!}</div>
		@endif
	</div>
	<form class="meros-site-form">
		@foreach(($formRows ?? []) as $formRow)
			@if(($formRow['_type'] ?? null) === 'group')
				@php
					$group = $formRow['group'] ?? [];
				@endphp

				<section class="meros-site-form-group" @if(!empty($group['id'])) id="{{ $group['id'] }}" @endif>
					@if(!empty($group['title']) || !empty($group['description']))
						<div class="meros-site-form-group-header">
							@if(!empty($group['title']))
								<h3>{{ $group['title'] }}</h3>
							@endif

							@if(!empty($group['description']))
								<p>{!! $this->renderQuillContent($group['description']) !!}</p>
							@endif
						</div>
					@endif

					@foreach(($group['rows'] ?? []) as $groupRow)
						<div class="meros-site-form-row">
							@foreach($groupRow['fields'] ?? [] as $fieldItem)
								@php
									$field = is_array($fieldItem) ? ($fieldItem['field'] ?? null) : $fieldItem;
								@endphp
								@if($field)
									<div class="meros-site-form-field">
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
				</section>
			@else
				<div class="meros-site-form-row">
					@foreach(($formRow['fields'] ?? []) as $fieldItem)
						@php
							$field = is_array($fieldItem) ? ($fieldItem['field'] ?? null) : $fieldItem;
						@endphp
						@if($field)
							<div class="meros-site-form-field">
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
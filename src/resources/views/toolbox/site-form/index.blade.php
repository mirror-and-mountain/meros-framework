<form class="meros-site-form">
	<div wire:click="testClick">{{ $test }}</div>
	@foreach(($formRows ?? []) as $formRow)
		@if(($formRow['_type'] ?? null) === 'group')
			@php
				$group = $formRow['group'] ?? [];
			@endphp

			<section class="meros-site-form-group" @if(!empty($group['id'])) id="{{ $group['id'] }}" @endif>
				@if(!empty($group['title']) || !empty($group['description']))
					<header class="meros-site-form-group-header">
						@if(!empty($group['title']))
							<h3>{{ $group['title'] }}</h3>
						@endif

						@if(!empty($group['description']))
							<p>{{ $group['description'] }}</p>
						@endif
					</header>
				@endif

				@foreach(($group['rows'] ?? []) as $groupRowFields)
					<div class="meros-site-form-row">
						@foreach($groupRowFields as $fieldItem)
							@php
								$field = is_array($fieldItem) ? ($fieldItem['field'] ?? null) : $fieldItem;
								$location = is_array($fieldItem) ? ($fieldItem['location'] ?? null) : null;
							@endphp
							@if($field)
								<div class="meros-site-form-field">
									@if(($field->handle ?? null) === 'repeater' && is_array($location))
										{!! $field->render(true, true, $location) !!}
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
						$location = is_array($fieldItem) ? ($fieldItem['location'] ?? null) : null;
					@endphp
					@if($field)
						<div class="meros-site-form-field">
							@if(($field->handle ?? null) === 'repeater' && is_array($location))
								{!! $field->render(true, true, $location) !!}
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
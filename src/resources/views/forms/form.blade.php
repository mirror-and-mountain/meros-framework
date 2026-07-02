<div x-data="{ isEditor: false }" class="meros-form-container">
	<div class="meros-form-header">
		@if($showTitle)
			<h1 class="meros-form-title">{{ $formTitle }}</h1>
		@endif
		@if($showDescription && !empty($formDescription))
			<div class="meros-form-description">{!! $formDescription !!}</div>
		@endif
	</div>

	<form class="meros-form">
		@php
			$pages = [];
			$ungroupedRows = [];
			$ungroupedPageIndex = null;

			foreach ($rows as $row) {
				if (($row->type ?? null) === 'group') {
					$pages[] = [
						'type' => 'group',
						'row' => $row,
					];
					continue;
				}

				if ($ungroupedPageIndex === null) {
					$ungroupedPageIndex = count($pages);
					$pages[] = [
						'type' => 'synthetic',
					];
				}

				$ungroupedRows[] = $row;
			}
		@endphp
		@foreach($pages as $currentPageIndex => $page)
			@if(($page['type'] ?? null) === 'group')
				@php
					$group = $page['row']->group;
					$groupId = $group->id;
					$groupRows = $group->rows;
				@endphp

				@if($currentPageIndex === $activeGroupPage)
					<div
						class="meros-form-group meros-form-group-page-transition meros-form-group-page-transition--{{ $groupPageDirection }}"
						data-meros-group-page="{{ $currentPageIndex }}"
						wire:key="meros-group-page-{{ $currentPageIndex }}"
						@if(!empty($groupId)) id="{{ $groupId }}" @endif
					>
					@if($totalGroupPages > 1)
						<div class="meros-form-group-page-header">
							<div class="meros-form-page-status">Group {{ $activeGroupPage + 1 }} of {{ $totalGroupPages }}</div>
							<div class="meros-form-page-progress" aria-hidden="true">
								<span class="meros-form-page-progress-bar" style="width: {{ (($activeGroupPage + 1) / max($totalGroupPages, 1)) * 100 }}%;"></span>
							</div>
						</div>
					@endif

					@if(!empty($group->title) || !empty($group->description))
						<div class="meros-form-group-header">
							@if(!empty($group->title))
								<h3>{{ $group->title }}</h3>
							@endif

							{{-- @if(!empty($group['description']))
								<p>{!! $this->renderQuillContent($group['description']) !!}</p>
							@endif --}}
						</div>
					@endif

					@foreach($groupRows as $groupRow)
						<div class="meros-form-row">
							@foreach($groupRow->fields ?? [] as $field)
								@if($field)
									<div class="meros-form-field">
										@if(($field->handle ?? null) === 'repeater')
											{!! $field->render() !!}
										@else
											{!! $field->render() !!}
										@endif
									</div>
								@endif
							@endforeach
						</div>
					@endforeach

					@if($totalGroupPages > 1)
						<div class="meros-form-group-page-actions">
							<button type="button" class="meros-form-page-button" wire:click="prevGroupPage" @if($activeGroupPage === 0) disabled @endif>Previous</button>
							<button type="button" class="meros-form-page-button" wire:click="nextGroupPage" @if($activeGroupPage >= $totalGroupPages - 1) disabled @endif>Next</button>
						</div>
					@endif
					</div>
				@endif

			@elseif(($page['type'] ?? null) === 'synthetic')
				@if($currentPageIndex === $activeGroupPage)
					<div
						class="meros-form-group meros-form-group--synthetic meros-form-group-page-transition meros-form-group-page-transition--{{ $groupPageDirection }}"
						data-meros-group-page="{{ $currentPageIndex }}"
						wire:key="meros-group-page-{{ $currentPageIndex }}"
					>
						@if($totalGroupPages > 1)
							<div class="meros-form-group-page-header">
								<div class="meros-form-page-status">Group {{ $activeGroupPage + 1 }} of {{ $totalGroupPages }}</div>
								<div class="meros-form-page-progress" aria-hidden="true">
									<span class="meros-form-page-progress-bar" style="width: {{ (($activeGroupPage + 1) / max($totalGroupPages, 1)) * 100 }}%;"></span>
								</div>
							</div>
						@endif

						@foreach($ungroupedRows as $ungroupedRow)
							<div class="meros-form-row">
								@foreach($ungroupedRow->fields ?? [] as $field)
									@if($field)
										<div class="meros-form-field">
											@if(($field->handle ?? null) === 'repeater')
												{!! $field->render() !!}
											@else
												{!! $field->render() !!}
											@endif
										</div>
									@endif
								@endforeach
							</div>
						@endforeach

						@if($totalGroupPages > 1)
							<div class="meros-form-group-page-actions">
								<button type="button" class="meros-form-page-button" wire:click="prevGroupPage" @if($activeGroupPage === 0) disabled @endif>Previous</button>
								<button type="button" class="meros-form-page-button" wire:click="nextGroupPage" @if($activeGroupPage >= $totalGroupPages - 1) disabled @endif>Next</button>
							</div>
						@endif
					</div>
				@endif
			@endif
		@endforeach
	</form>
</div>
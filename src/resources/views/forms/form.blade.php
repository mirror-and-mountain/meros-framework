<div x-data="{ isEditor: false }" class="meros-form-container">
	<div class="meros-form-header">
		@if($showTitle)
			<h1 class="meros-form-title">{{ $formTitle }}</h1>
		@endif
		@if($showDescription && !empty($formDescription))
			<div class="meros-form-description">{!! $formDescription !!}</div>
		@endif
	</div>

	@if(!$isPagedView && $totalGroupPages > 1)
		<div class="meros-form-view-toggle" role="group" aria-label="Form view mode">
			<button
				type="button"
				class="meros-form-view-toggle-button @if($isPagedView) is-active @endif"
				wire:click="setPagedView"
				aria-label="Paged view"
				title="Paged view"
				aria-pressed="@if($isPagedView) true @else false @endif"
			>
				<svg class="meros-form-view-toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M4 6h10v12H4zM10 4h10v12h-4" />
				</svg>
				<span class="meros-sr-only">Paged view</span>
			</button>
			<button
				type="button"
				class="meros-form-view-toggle-button @if(!$isPagedView) is-active @endif"
				wire:click="setFullView"
				aria-label="View all"
				title="View all"
				aria-pressed="@if(!$isPagedView) true @else false @endif"
			>
				<svg class="meros-form-view-toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
					<path d="M4 7h16M4 12h16M4 17h16" />
				</svg>
				<span class="meros-sr-only">View all</span>
			</button>
		</div>
	@endif

	<form class="meros-form @if($isPagedView) is-paged-view @else is-full-view @endif">
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
					$groupTitle = trim((string) ($group->title ?? ''));
					$groupDescription = (string) ('This is a test group description.');
					$hasGroupDescription = trim(strip_tags($groupDescription)) !== '';
					$fullViewGroupHeading = $groupTitle !== '' ? $groupTitle : ('Group ' . ($currentPageIndex + 1));
				@endphp

				@if(!$isPagedView || $currentPageIndex === $activeGroupPage)
					<div
						class="meros-form-group"
						data-meros-group-page="{{ $currentPageIndex }}"
						wire:key="meros-group-page-{{ $currentPageIndex }}"
						@if(!empty($groupId)) id="{{ $groupId }}" @endif
					>
					@if($isPagedView && $totalGroupPages > 1)
						<div class="meros-form-group-page-header">
							<div class="meros-form-group-page-meta">
								<div class="meros-form-page-status">
									Page {{ $activeGroupPage + 1 }} of {{ $totalGroupPages }}
								</div>
								<div class="meros-form-page-progress" aria-hidden="true">
									<span class="meros-form-page-progress-bar" style="width: {{ (($activeGroupPage + 1) / max($totalGroupPages, 1)) * 100 }}%;"></span>
								</div>
							</div>
							<div class="meros-form-view-toggle" role="group" aria-label="Form view mode">
								<button
									type="button"
									class="meros-form-view-toggle-button @if($isPagedView) is-active @endif"
									wire:click="setPagedView"
									aria-label="Paged view"
									title="Paged view"
									aria-pressed="@if($isPagedView) true @else false @endif"
								>
									<svg class="meros-form-view-toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
										<path d="M4 6h10v12H4zM10 4h10v12h-4" />
									</svg>
									<span class="meros-sr-only">Paged view</span>
								</button>
								<button
									type="button"
									class="meros-form-view-toggle-button @if(!$isPagedView) is-active @endif"
									wire:click="setFullView"
									aria-label="View all"
									title="View all"
									aria-pressed="@if(!$isPagedView) true @else false @endif"
								>
									<svg class="meros-form-view-toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
										<path d="M4 7h16M4 12h16M4 17h16" />
									</svg>
									<span class="meros-sr-only">View all</span>
								</button>
							</div>
						</div>
					@endif

					@if(!$isPagedView || $groupTitle !== '' || $hasGroupDescription)
						<div class="meros-form-group-header">
							@if(!$isPagedView || $groupTitle !== '')
								<h3>@if($isPagedView){{ $groupTitle }}@else{{ $fullViewGroupHeading }}@endif</h3>
							@endif

							@if($hasGroupDescription)
								<p>{!! $groupDescription !!}</p>
							@endif
						</div>
					@endif

					<div class="meros-form-group-page-body @if($isPagedView && $currentPageIndex === $activeGroupPage) meros-form-group-page-transition meros-form-group-page-transition--{{ $groupPageDirection }} @endif">

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

					@if($isPagedView && $totalGroupPages > 1)
						<div class="meros-form-group-page-actions">
							<button type="button" class="meros-form-page-button" wire:click="prevGroupPage" @if($activeGroupPage === 0) disabled @endif>Previous</button>
							<button type="button" class="meros-form-page-button" wire:click="nextGroupPage" @if($activeGroupPage >= $totalGroupPages - 1) disabled @endif>Next</button>
							<button type="submit" class="mt-form-submit-button" @if($activeGroupPage < $totalGroupPages - 1) disabled @endif>Submit</button>
						</div>
					@endif
					</div>
					</div>
				@endif

			@elseif(($page['type'] ?? null) === 'synthetic')
				@php
					$fullViewGroupHeading = 'Group ' . ($currentPageIndex + 1);
				@endphp
				@if(!$isPagedView || $currentPageIndex === $activeGroupPage)
					<div
						class="meros-form-group meros-form-group--synthetic"
						data-meros-group-page="{{ $currentPageIndex }}"
						wire:key="meros-group-page-{{ $currentPageIndex }}"
					>
						@if($isPagedView && $totalGroupPages > 1)
							<div class="meros-form-group-page-header">
								<div class="meros-form-group-page-meta">
									<div class="meros-form-page-status">Page {{ $activeGroupPage + 1 }} of {{ $totalGroupPages }}</div>
									<div class="meros-form-page-progress" aria-hidden="true">
										<span class="meros-form-page-progress-bar" style="width: {{ (($activeGroupPage + 1) / max($totalGroupPages, 1)) * 100 }}%;"></span>
									</div>
								</div>
								<div class="meros-form-view-toggle" role="group" aria-label="Form view mode">
									<button
										type="button"
										class="meros-form-view-toggle-button @if($isPagedView) is-active @endif"
										wire:click="setPagedView"
										aria-label="Paged view"
										title="Paged view"
										aria-pressed="@if($isPagedView) true @else false @endif"
									>
										<svg class="meros-form-view-toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path d="M4 6h10v12H4zM10 4h10v12h-4" />
										</svg>
										<span class="meros-sr-only">Paged view</span>
									</button>
									<button
										type="button"
										class="meros-form-view-toggle-button @if(!$isPagedView) is-active @endif"
										wire:click="setFullView"
										aria-label="View all"
										title="View all"
										aria-pressed="@if(!$isPagedView) true @else false @endif"
									>
										<svg class="meros-form-view-toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
											<path d="M4 7h16M4 12h16M4 17h16" />
										</svg>
										<span class="meros-sr-only">View all</span>
									</button>
								</div>
							</div>
						@endif

						@if(!$isPagedView)
							<div class="meros-form-group-header">
								<h3>{{ $fullViewGroupHeading }}</h3>
							</div>
						@endif

						<div class="meros-form-group-page-body @if($isPagedView && $currentPageIndex === $activeGroupPage) meros-form-group-page-transition meros-form-group-page-transition--{{ $groupPageDirection }} @endif">

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

						@if($isPagedView && $totalGroupPages > 1)
							<div class="meros-form-group-page-actions">
								<button type="button" class="meros-form-page-button" wire:click="prevGroupPage" @if($activeGroupPage === 0) disabled @endif>Previous</button>
								<button type="button" class="meros-form-page-button" wire:click="nextGroupPage" @if($activeGroupPage >= $totalGroupPages - 1) disabled @endif>Next</button>
								<button type="submit" class="mt-form-submit-button" @if($activeGroupPage < $totalGroupPages - 1) disabled @endif>Submit</button>
							</div>
						@endif
						</div>
					</div>
				@endif
			@endif
		@endforeach

		@if(!$isPagedView)
			<div class="meros-form-group-page-actions">
				<button type="submit" class="mt-form-submit-button">Submit</button>
			</div>
		@endif
	</form>
</div>
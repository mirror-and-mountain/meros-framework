<div class="meros-table-card">
    <div class="meros-table-card-header">
        <div class="meros-table-card-entry">
            <h3 class="meros-table-card-title">{{ $label }}</h3>
            <small>{{ $name }}</small>
        </div>
        <div class="meros-table-card-status">
            <p>
                @if($installed && !$hasUpdates)
                    <span class="installed">Installed</span>
                @elseif($installed && $hasUpdates)
                    <span class="has-updates">Updates Available</span>
                @else
                    <span class="not-installed">Not Installed</span>
                @endif
                @if($required)
                    <span> (Required)</span>
                @endif
            </p>
        </div>
    </div>
    <div class="meros-table-card-info">
        @if($required)
            <p class="meros-table-card-required">This table is required and is installed automatically @if($isPackage) when the package is enabled.@else.@endif</p>
        @endif
        @if(!empty($description))
            <p class="meros-table-card-description">{{ $description }}</p>
        @endif
        @if(!$installed && !empty($dependencies))
            <div>
                <strong>The following tables need to be installed before this one:</strong>
                <div>
                    {!! implode('<br>', $dependencies) !!}
                </div>
            </div>
        @elseif($installed && !empty($dependencies))
            <div style="margin-bottom: 1rem;">
                <strong>This table depends on the following tables:</strong>
                <div>
                    {!! implode('<br>', $dependencies) !!}
                </div>
            </div>
        @endif
        @if($installed)
            <div><span><strong>Installed At: </strong>{{ $installedAt }}</span></div>
            @if($lastUpdatedAt)
                <div><span><strong>Last Updated At: </strong>{{ $lastUpdatedAt }}</span></div>
            @endif
        @endif
    </div>
    <div class="meros-table-card-actions">
        @if($isPackage)
            @if($enabled && $canInstall && !$installed)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Install the table into the database',
                    'name'   => $name,
                    'action' => 'install',
                    'nonce'  => $nonce,
                    'label'  => 'Install',
                    'provider' => $provider,
                ])
            @endif
            @if($enabled && $installed && $hasUpdates)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Update the table to the latest version',
                    'name'   => $name,
                    'action' => 'update',
                    'nonce'  => $nonce,
                    'label'  => 'Update',
                    'provider' => $provider,
                ])
            @endif
            @if($enabled && $installed && $canRollback)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Rollback the table to the previous version',
                    'name'   => $name,
                    'action' => 'rollback',
                    'nonce'  => $nonce,
                    'label'  => 'Rollback',
                    'provider' => $provider,
                ])
            @endif
            @if(!$enabled && $installed)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Uninstall the table from the database',
                    'name'   => $name,
                    'action' => 'uninstall',
                    'nonce'  => $nonce,
                    'label'  => 'Uninstall',
                    'provider' => $provider,
                ])
            @endif
        @else
            @if($installed && !$required)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Uninstall the table from the database',
                    'name'   => $name,
                    'action' => 'uninstall',
                    'nonce'  => $nonce,
                    'label'  => 'Uninstall',
                    'provider' => $provider,
                ])
            @endif
            @if($canInstall && !$installed)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Install the table into the database',
                    'name'   => $name,
                    'action' => 'install',
                    'nonce'  => $nonce,
                    'label'  => 'Install',
                    'provider' => $provider,
                ])
            @endif
            @if($installed && $hasUpdates)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Update the table to the latest version',
                    'name'   => $name,
                    'action' => 'update',
                    'nonce'  => $nonce,
                    'label'  => 'Update',
                    'provider' => $provider,
                ])
            @endif
            @if($installed && $canRollback)
                @include('meros::admin.table-action-button', [
                    'title'  => 'Rollback the table to the previous version',
                    'name'   => $name,
                    'action' => 'rollback',
                    'nonce'  => $nonce,
                    'label'  => 'Rollback',
                    'provider' => $provider,
                ])
            @endif
        @endif
    </div>
    @if($hasUpdates && $updates !== null)
        <div class="meros-table-card-updates" style="display:none;" data-updates="{{ $updates }}"></div>
    @endif
    @if($canRollback && $rollback !== null)
        <div class="meros-table-card-rollback" style="display:none;" data-rollback="{{ $rollback }}"></div>
    @endif
</div>
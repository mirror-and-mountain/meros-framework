<form id="{{ $id }}" action="{{ $action }}" method="{{ $method }}" class="meros-form">
    <div class="meros-form-header">
        <h2>{{ $title }}</h2>
        @if($description)
            <p class="meros-form-description">{{ $description }}</p>
        @endif
    </div>
    <div class="meros-form-body">
        @foreach($elements as $element)
            {!! $element->render() !!}
        @endforeach
    </div>
</form>
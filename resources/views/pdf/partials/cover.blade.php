<div class="cover-page">
    {{-- Agent Branding --}}
    <div class="cover-header">
        @if($agent->avatar_url)
            <img src="{{ $agent->avatar_url }}" alt="{{ $agent->display_name }}" class="agent-avatar">
        @else
            <div class="agent-avatar-placeholder">
                {{ substr($agent->display_name, 0, 1) }}
            </div>
        @endif
        <div class="agent-name">{{ $agent->display_name }}</div>
        @if($agent->tagline)
            <div class="agent-tagline">{{ $agent->tagline }}</div>
        @endif
    </div>

    {{-- Collection Info --}}
    <div class="cover-content">
        <div class="collection-label">Coleccion Exclusiva</div>
        <h1 class="collection-title">{{ $collection->name }}</h1>
        <div class="collection-meta">
            <div class="meta-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                </svg>
                {{ $properties->count() }} {{ $properties->count() === 1 ? 'propiedad' : 'propiedades' }}
            </div>
            @if($collection->client)
                <div class="meta-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Preparado para <strong>{{ $collection->client->name }}</strong>
                </div>
            @endif
        </div>
    </div>

    {{-- Contact Footer --}}
    <div class="cover-footer">
        <div class="cover-contact">
            @if($agent->whatsapp)
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#25D366">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    {{ $agent->whatsapp }}
                </div>
            @endif
            @if($agent->email)
                <div class="meta-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="M22 6l-10 7L2 6"/>
                    </svg>
                    {{ $agent->email }}
                </div>
            @endif
        </div>
    </div>
</div>

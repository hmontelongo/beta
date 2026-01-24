<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $collection->name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            padding: 20px;
            border-bottom: 2px solid #3b82f6;
            margin-bottom: 20px;
        }
        .agent-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .agent-name {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
        }
        .agent-label {
            font-size: 10px;
            color: #6b7280;
        }
        .agent-contact {
            font-size: 10px;
            color: #6b7280;
            margin-top: 4px;
        }
        .collection-title {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 15px;
        }
        .collection-meta {
            font-size: 11px;
            color: #6b7280;
            margin-top: 5px;
        }
        .properties {
            padding: 0 20px;
        }
        .property {
            page-break-inside: avoid;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        .property-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background-color: #f3f4f6;
        }
        .property-content {
            padding: 12px;
        }
        .property-price {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
        }
        .property-location {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }
        .property-specs {
            font-size: 10px;
            color: #6b7280;
            margin-top: 8px;
        }
        .property-specs span {
            margin-right: 12px;
        }
        .footer {
            margin-top: 30px;
            padding: 15px 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
        .two-columns {
            width: 100%;
        }
        .two-columns td {
            width: 50%;
            vertical-align: top;
            padding: 0 5px;
        }
    </style>
</head>
<body>
    {{-- Header with Agent Info --}}
    <div class="header">
        <div class="agent-name">{{ $agent->name }}</div>
        <div class="agent-label">Agente Inmobiliario</div>
        @if($agent->whatsapp || $agent->email)
            <div class="agent-contact">
                @if($agent->whatsapp){{ $agent->whatsapp }}@endif
                @if($agent->whatsapp && $agent->email) · @endif
                @if($agent->email){{ $agent->email }}@endif
            </div>
        @endif

        <div class="collection-title">{{ $collection->name }}</div>
        <div class="collection-meta">
            {{ $collection->properties->count() }} {{ $collection->properties->count() === 1 ? 'propiedad' : 'propiedades' }}
            @if($collection->client)
                · Para: {{ $collection->client->name }}
            @endif
        </div>
    </div>

    {{-- Properties Grid (2 columns using table for PDF compatibility) --}}
    <div class="properties">
        <table class="two-columns">
            @foreach($collection->properties->chunk(2) as $row)
                <tr>
                    @foreach($row as $property)
                        @php
                            $listing = $property->listings->first();
                            $images = $listing?->raw_data['images'] ?? [];
                            $heroImage = $images[0] ?? null;
                            $operations = $listing?->operations ?? [];
                            $price = $operations[0]['price'] ?? null;
                            $opType = $operations[0]['type'] ?? null;
                        @endphp
                        <td>
                            <div class="property">
                                @if($heroImage)
                                    <img src="{{ $heroImage }}" alt="" class="property-image" />
                                @else
                                    <div class="property-image" style="display: flex; align-items: center; justify-content: center; color: #9ca3af;">
                                        Sin imagen
                                    </div>
                                @endif
                                <div class="property-content">
                                    <div class="property-price">
                                        @if($price)
                                            ${{ number_format($price) }}{{ $opType === 'rent' ? '/mes' : '' }}
                                        @else
                                            Consultar precio
                                        @endif
                                    </div>
                                    <div class="property-location">
                                        {{ $property->colonia }}{{ $property->city ? ', ' . $property->city : '' }}
                                    </div>
                                    <div class="property-specs">
                                        @if($property->bedrooms)
                                            <span>{{ $property->bedrooms }} rec</span>
                                        @endif
                                        @if($property->bathrooms)
                                            <span>{{ $property->bathrooms }} ban</span>
                                        @endif
                                        @if($property->built_size_m2)
                                            <span>{{ number_format($property->built_size_m2) }} m²</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if($row->count() === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        Generado el {{ now()->format('d/m/Y') }} · {{ config('app.name') }}
    </div>
</body>
</html>

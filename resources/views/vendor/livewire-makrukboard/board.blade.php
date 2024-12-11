<!-- resources/views/vendor/livewire-makrukboard/board.blade.php -->
<div class="relative aspect-square w-full max-w-[600px]" wire:key="makruk-board" x-data="makrukBoard" x-init="initBoard">
    <!-- Coordinates (if enabled) -->
    @if($showCoordinates)
    <div class="absolute inset-0 pointer-events-none">
        <div class="flex justify-between px-1 text-xs">
            @foreach($coordinates['files'] as $file)
            <span>{{ $file }}</span>
            @endforeach
        </div>
        <div class="absolute left-0 top-0 bottom-0 flex flex-col justify-between py-1 text-xs">
            @foreach($coordinates['ranks'] as $rank)
            <span>{{ $rank }}</span>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Board squares -->
    <div class="grid grid-cols-8 h-full w-full">
        @for ($row = 0; $row < 8; $row++) @for ($col=0; $col < 8; $col++) @php $square=chr(97 + $col) . (8 - $row); $isLight=($row + $col) % 2===0; $isSelected=$selectedSquare===$square; $isValidMove=in_array($square, $validMoves); $isLastMove=in_array($square, $lastMove); @endphp <div x-ref="square_{{ $square }}" data-square="{{ $square }}" class="relative aspect-square 
                           {{ $isLight ? $squares['light'] : $squares['dark'] }}
                           {{ $isSelected ? $squares['selected'] : '' }}
                           {{ $isValidMove ? $squares['valid_move'] : '' }}
                           {{ $isLastMove ? $squares['last_move'] : '' }}" @if($enableDragDrop) @dragover.prevent @drop.prevent="onDrop($event, '{{ $square }}')" @endif @click="onSquareClick('{{ $square }}')">
            @if($piece = $this->getPieceOnSquare($square))
            <div class="piece-container" style="transition: transform {{ $animationDuration }}" @if($enableDragDrop) draggable="true" @dragstart="onDragStart($event, '{{ $square }}')" @dragend="onDragEnd" @endif>
                <svg class="w-full h-full" viewBox="0 0 45 45">
                    <use href="#{{ strtolower($piece) }}" />
                </svg>

                @if($showPieceValues)
                <span class="absolute bottom-0 right-0 text-xs bg-black bg-opacity-50 text-white px-1 rounded">
                    {{ $pieceValues[strtolower($piece)] }}
                </span>
                @endif
            </div>
            @endif

            @if($showMoveHints && $isValidMove)
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-3 h-3 rounded-full bg-green-500 opacity-50"></div>
            </div>
            @endif
    </div>
    @endfor
    @endfor
</div>

<!-- Captured pieces display -->
<div class="mt-4 flex justify-between text-sm">
    <div>
        @foreach($capturedPieces['white'] as $piece)
        <svg class="w-6 h-6 inline-block" viewBox="0 0 45 45">
            <use href="#{{ strtolower($piece) }}" />
        </svg>
        @endforeach
    </div>
    <div>
        @foreach($capturedPieces['black'] as $piece)
        <svg class="w-6 h-6 inline-block" viewBox="0 0 45 45">
            <use href="#{{ strtolower($piece) }}" />
        </svg>
        @endforeach
    </div>
</div>

<!-- Move counter -->
@if($enableCountMoves)
<div class="mt-2 text-center text-sm">
    Move: {{ $moveCount }}
</div>
@endif

<!-- SVG Definitions -->
<svg class="hidden">
    <defs>
        @include('livewire-makrukboard::pieces')
    </defs>
</svg>
</div>
@if($isCheckmate)
<div class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white p-4 rounded-lg shadow-lg text-center">
        <h2 class="text-xl font-bold mb-2">Checkmate!</h2>
        <p>{{ strpos($fen, 'w') !== false ? 'Black' : 'White' }} wins!</p>
    </div>
</div>
@elseif($isCheck)
<div class="absolute top-4 left-1/2 transform -translate-x-1/2 bg-red-500 text-white px-4 py-2 rounded-full z-40">
    Check!
</div>
@endif

<!-- Game state indicator -->
<div class="mt-4 text-center">
    @if($isCheckmate)
    <span class="text-red-600 font-bold">Checkmate - {{ strpos($fen, 'w') !== false ? 'Black' : 'White' }} wins!</span>
    @elseif($isCheck)
    <span class="text-red-600">Check!</span>
    @else
    <span>{{ strpos($fen, 'w') !== false ? 'White' : 'Black' }} to move</span>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('makrukBoard', () => ({
            draggingPiece: null,

            initBoard() {
                // Initialize sounds if enabled
                if (@json(Config::get('makrukboard.events.enable_move_sound'))) {
                    // Initialize move sound
                }
                if (@json(Config::get('makrukboard.events.enable_capture_sound'))) {
                    // Initialize capture sound
                }
                if (@json(Config::get('makrukboard.events.enable_check_sound'))) {
                    // Initialize check sound
                }
            },

            onSquareClick(square) {
                @this.onSquareClick(square);
            },

            onDragStart(event, square) {
                if (!@json($enableDragDrop)) return;

                this.draggingPiece = square;
                @this.onPieceDragStart(square);

                const svg = event.target.querySelector('svg');
                const dragImage = svg.cloneNode(true);
                dragImage.style.width = '45px';
                dragImage.style.height = '45px';
                document.body.appendChild(dragImage);
                event.dataTransfer.setDragImage(dragImage, 22, 22);
                setTimeout(() => document.body.removeChild(dragImage), 0);
            },

            onDragEnd() {
                this.draggingPiece = null;
            },

            onDrop(event, square) {
                if (!@json($enableDragDrop)) return;

                if (this.draggingPiece) {
                    @this.onPieceDrop(square);
                }
            }
        }));
    });

    document.addEventListener('livewire:initialized', () => {
        @this.on('makrukboard-check', () => {
            if (@json(Config::get('makrukboard.events.enable_check_sound'))) {
                // Play check sound
                new Audio('/vendor/livewire-makrukboard/sounds/check.mp3').play();
            }
        });

        @this.on('makrukboard-checkmate', () => {
            // Play checkmate sound
            new Audio('/vendor/livewire-makrukboard/sounds/checkmate.mp3').play();
        });
    });

</script>
@endpush

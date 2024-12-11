<?php

namespace YourVendor\LivewireMakrukboard;

use Livewire\Component;
use Illuminate\Support\Facades\Config;

class MakrukBoard extends Component
{
    // Properties from config
    public string $fen;
    public bool $showCoordinates;
    public bool $showMoveHints;
    public bool $showPieceValues;
    public bool $enableDragDrop;
    public bool $enablePremove;
    public int $animationSpeed;
    public array $theme;

    // Game state
    public array $validMoves = [];
    public ?string $selectedSquare = null;
    public bool $isDragging = false;
    public array $lastMove = [];
    public int $moveCount = 0;
    public array $capturedPieces = ['white' => [], 'black' => []];

    protected array $pieceValues;
    protected array $promotionRanks;
    protected bool $enableCountMoves;
    public bool $isCheck = false;
    public bool $isCheckmate = false;

    public function mount(
        ?string $fen = null,
        ?array $theme = null,
        ?bool $showCoordinates = null,
        ?bool $showMoveHints = null,
        ?bool $showPieceValues = null,
        ?bool $enableDragDrop = null,
        ?bool $enablePremove = null,
        ?int $animationSpeed = null
    ) {
        // Load from config with optional overrides
        $this->fen = $fen ?? Config::get('makrukboard.default_position');
        $this->theme = $theme ?? Config::get('makrukboard.theme');
        $this->showCoordinates = $showCoordinates ?? Config::get('makrukboard.interface.show_coordinates');
        $this->showMoveHints = $showMoveHints ?? Config::get('makrukboard.interface.show_move_hints');
        $this->showPieceValues = $showPieceValues ?? Config::get('makrukboard.interface.show_piece_values');
        $this->enableDragDrop = $enableDragDrop ?? Config::get('makrukboard.interface.enable_drag_drop');
        $this->enablePremove = $enablePremove ?? Config::get('makrukboard.interface.enable_premove');
        $this->animationSpeed = $animationSpeed ?? Config::get('makrukboard.interface.animation_speed');

        // Load rule configurations
        $this->pieceValues = Config::get('makrukboard.rules.piece_values');
        $this->promotionRanks = Config::get('makrukboard.rules.promotion_rank');
        $this->enableCountMoves = Config::get('makrukboard.rules.enable_countmoves');
    }

    public function getSquareClassesProperty()
    {
        return [
            'light' => $this->theme['light_square'],
            'dark' => $this->theme['dark_square'],
            'selected' => $this->theme['selected'],
            'valid_move' => $this->theme['valid_move'],
            'last_move' => $this->theme['last_move'],
        ];
    }

    public function getPieceValue($piece): int
    {
        return $this->pieceValues[strtolower($piece)] ?? 0;
    }

    protected function dispatchBoardEvents(bool $isCapture): void
    {
        parent::dispatchBoardEvents($isCapture);

        if (Config::get('makrukboard.events.enable_move_sound')) {
            $this->dispatch('makrukboard-move');
        }

        if ($isCapture && Config::get('makrukboard.events.enable_capture_sound')) {
            $this->dispatch('makrukboard-capture');
        }

        if ($this->isInCheck() && Config::get('makrukboard.events.enable_check_sound')) {
            $this->dispatch('makrukboard-check');
        }

        if ($this->isCheckmate) {
            $winner = strpos($this->fen, 'w') !== false ? 'Black' : 'White';
            $this->dispatch('makrukboard-checkmate', ['winner' => $winner]);
        } elseif ($this->isCheck) {
            $this->dispatch('makrukboard-check');
        }

        $this->dispatch('makrukboard-position-change', [
            'fen' => $this->fen,
            'lastMove' => $this->lastMove,
            'moveCount' => $this->moveCount,
        ]);
    }

    protected function isInCheck(bool $forWhite = null): bool
    {
        // ถ้าไม่ระบุสี ให้ตรวจสอบสีที่ต้องเดิน
        if ($forWhite === null) {
            $forWhite = strpos($this->fen, 'w') !== false;
        }

        $position = $this->fenToPosition($this->fen);
        $kingSquare = $this->findKing($position, $forWhite);

        if (!$kingSquare) {
            return false;
        }

        // ตรวจสอบการคุกคามจากหมากทุกตัวของฝ่ายตรงข้าม
        for ($rank = 0; $rank < 8; $rank++) {
            for ($file = 0; $file < 8; $file++) {
                $piece = $position[$rank][$file];
                if ($piece === ' ')
                    continue;

                // ตรวจสอบเฉพาะหมากฝ่ายตรงข้าม
                if ($forWhite && ctype_lower($piece)) {
                    $square = chr($file + ord('a')) . (8 - $rank);
                    $moves = $this->calculateValidMoves($square);
                    if (in_array($kingSquare, $moves)) {
                        return true;
                    }
                } elseif (!$forWhite && ctype_upper($piece)) {
                    $square = chr($file + ord('a')) . (8 - $rank);
                    $moves = $this->calculateValidMoves($square);
                    if (in_array($kingSquare, $moves)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function isCheckmate(): bool
    {
        $currentPlayer = strpos($this->fen, 'w') !== false;

        if (!$this->isInCheck($currentPlayer)) {
            return false;
        }

        // ตรวจสอบการหนีรุกทุกตาที่เป็นไปได้
        $position = $this->fenToPosition($this->fen);
        for ($rank = 0; $rank < 8; $rank++) {
            for ($file = 0; $file < 8; $file++) {
                $piece = $position[$rank][$file];
                if ($piece === ' ')
                    continue;

                // ตรวจสอบเฉพาะหมากฝ่ายที่ถูกรุก
                if (
                    ($currentPlayer && ctype_upper($piece)) ||
                    (!$currentPlayer && ctype_lower($piece))
                ) {
                    $fromSquare = chr($file + ord('a')) . (8 - $rank);
                    $moves = $this->calculateValidMoves($fromSquare);

                    // ลองเดินทุกตาที่เป็นไปได้
                    foreach ($moves as $toSquare) {
                        if ($this->canMoveToAvoidCheck($fromSquare, $toSquare)) {
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    protected function canMoveToAvoidCheck(string $from, string $to): bool
    {
        // บันทึกสถานะปัจจุบัน
        $originalFen = $this->fen;
        $originalPosition = $this->fenToPosition($this->fen);

        // จำลองการเดิน
        $this->makeMove($from, $to);

        // ตรวจสอบว่ายังถูกรุกอยู่หรือไม่
        $stillInCheck = $this->isInCheck(strpos($originalFen, 'w') !== false);

        // คืนค่าสถานะเดิม
        $this->fen = $originalFen;

        return !$stillInCheck;
    }

    protected function findKing(array $position, bool $white): ?string
    {
        $kingChar = $white ? 'K' : 'k';

        for ($rank = 0; $rank < 8; $rank++) {
            for ($file = 0; $file < 8; $file++) {
                if ($position[$rank][$file] === $kingChar) {
                    return chr($file + ord('a')) . (8 - $rank);
                }
            }
        }

        return null;
    }

    protected function getCoordinates(): array
    {
        $files = range('a', 'h');
        $ranks = range(1, 8);
        return [
            'files' => $files,
            'ranks' => $ranks,
        ];
    }

    public function onSquareClick($square)
    {
        if ($this->selectedSquare === null) {
            if ($this->isPieceOnSquare($square) && $this->isCurrentPlayerPiece($square)) {
                $this->selectedSquare = $square;
                $this->validMoves = $this->calculateValidMoves($square);
            }
        } else {
            if (in_array($square, $this->validMoves)) {
                $this->makeMove($this->selectedSquare, $square);
                $this->dispatch('move', [
                    'from' => $this->selectedSquare,
                    'to' => $square,
                    'fen' => $this->fen
                ]);
            }
            $this->selectedSquare = null;
            $this->validMoves = [];
        }
    }

    public function onPieceDragStart($square)
    {
        if ($this->isPieceOnSquare($square) && $this->isCurrentPlayerPiece($square)) {
            $this->isDragging = true;
            $this->selectedSquare = $square;
            $this->validMoves = $this->calculateValidMoves($square);
        }
    }

    public function onPieceDrop($targetSquare)
    {
        if ($this->isDragging && $this->selectedSquare && in_array($targetSquare, $this->validMoves)) {
            $this->makeMove($this->selectedSquare, $targetSquare);
            $this->dispatch('move', [
                'from' => $this->selectedSquare,
                'to' => $targetSquare,
                'fen' => $this->fen
            ]);
        }
        $this->isDragging = false;
        $this->selectedSquare = null;
        $this->validMoves = [];
    }

    protected function isPieceOnSquare($square): bool
    {
        $position = $this->fenToPosition($this->fen);
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);
        return $position[$rankIndex][$fileIndex] !== ' ';
    }

    protected function isCurrentPlayerPiece($square): bool
    {
        $position = $this->fenToPosition($this->fen);
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);
        $piece = $position[$rankIndex][$fileIndex];
        $currentPlayer = strpos($this->fen, 'w') !== false ? 'white' : 'black';
        return $currentPlayer === 'white' ? ctype_upper($piece) : ctype_lower($piece);
    }

    protected function calculateValidMoves($square): array
    {
        $position = $this->fenToPosition($this->fen);
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);
        $piece = strtolower($position[$rankIndex][$fileIndex]);
        $isWhite = ctype_upper($position[$rankIndex][$fileIndex]);
        $validMoves = [];
        $moves = parent::calculateValidMoves($square);

        switch ($piece) {
            case 'k': // ขุน
                $directions = [[0, 1], [1, 1], [1, 0], [1, -1], [0, -1], [-1, -1], [-1, 0], [-1, 1]];
                $validMoves = $this->getMovesByDirections($square, $directions, 1);
                break;

            case 'm': // เม็ด
                $directions = [[1, 1], [1, -1], [-1, -1], [-1, 1]];
                $validMoves = $this->getMovesByDirections($square, $directions, 1);
                break;

            case 'n': // ม้า
                $knightMoves = [[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]];
                $validMoves = $this->getKnightMoves($square, $knightMoves);
                break;

            case 'r': // เรือ
                $directions = [[0, 1], [1, 0], [0, -1], [-1, 0]];
                $validMoves = $this->getMovesByDirections($square, $directions);
                break;

            case 's': // โคน
                // เดินเฉียง 4 ทิศ และตรงหน้า 1 ช่อง
                $directions = [[1, 1], [1, -1], [-1, -1], [-1, 1]];
                $validMoves = $this->getMovesByDirections($square, $directions, 1);

                // เพิ่มการเดินตรงหน้า 1 ช่อง
                $forwardDirection = $isWhite ? -1 : 1;
                $forwardMoves = $this->getMovesByDirections($square, [[0, $forwardDirection]], 1);
                $validMoves = array_merge($validMoves, $forwardMoves);
                break;

            case 'p': // เบี้ย
                $direction = $isWhite ? -1 : 1;
                // ตรวจสอบว่าถึงแถวเลื่อนขั้นหรือยัง
                $targetRank = $isWhite ? 3 : 6;
                if (8 - $rankIndex === $targetRank) {
                    // ถ้าถึงแถวเลื่อนขั้น ให้เดินเหมือนเม็ด
                    $directions = [[1, 1], [1, -1], [-1, -1], [-1, 1]];
                    $validMoves = $this->getMovesByDirections($square, $directions, 1);
                } else {
                    // ถ้ายังไม่ถึงแถวเลื่อนขั้น ให้เดินตรงหน้า 1 ช่อง
                    $moves = $this->getPawnMoves($square, $direction);
                    $validMoves = array_merge($validMoves, $moves);
                }
                break;
        }

        return array_values(array_filter($validMoves, function ($move) use($square) {
            return $this->isValidSquare($move) && !$this->isOwnPieceOnSquare($move) && /* กรองเฉพาะการเดินที่ไม่ทำให้ตัวเองถูกรุก */ $this->canMoveToAvoidCheck($square, $move);
        }));
    }

    protected function getMovesByDirections($square, array $directions, ?int $maxSteps = null): array
    {
        $moves = [];
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);

        foreach ($directions as [$dx, $dy]) {
            $steps = 1;
            while ($maxSteps === null || $steps <= $maxSteps) {
                $newFile = chr(ord('a') + ($fileIndex + $dx * $steps));
                $newRank = 8 - ($rankIndex + $dy * $steps);
                $newSquare = $newFile . $newRank;

                if (!$this->isValidSquare($newSquare)) {
                    break;
                }

                if ($this->isPieceOnSquare($newSquare)) {
                    if (!$this->isOwnPieceOnSquare($newSquare)) {
                        $moves[] = $newSquare;
                    }
                    break;
                }

                $moves[] = $newSquare;
                $steps++;
            }
        }

        return $moves;
    }

    protected function getKnightMoves($square, array $moves): array
    {
        $validMoves = [];
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);

        foreach ($moves as [$dx, $dy]) {
            $newFile = chr(ord('a') + ($fileIndex + $dx));
            $newRank = 8 - ($rankIndex + $dy);
            $newSquare = $newFile . $newRank;

            if ($this->isValidSquare($newSquare) && !$this->isOwnPieceOnSquare($newSquare)) {
                $validMoves[] = $newSquare;
            }
        }

        return $validMoves;
    }

    protected function getPawnMoves($square, int $direction): array
    {
        $moves = [];
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);

        // เดินหน้า 1 ช่อง
        $newRank = 8 - ($rankIndex + $direction);
        $newSquare = $file . $newRank;
        if ($this->isValidSquare($newSquare) && !$this->isPieceOnSquare($newSquare)) {
            $moves[] = $newSquare;
        }

        return $moves;
    }

    protected function isValidSquare($square): bool
    {
        if (strlen($square) !== 2)
            return false;
        [$file, $rank] = str_split($square);
        return $file >= 'a' && $file <= 'h' && $rank >= '1' && $rank <= '8';
    }

    protected function isOwnPieceOnSquare($square): bool
    {
        return $this->isPieceOnSquare($square) && $this->isCurrentPlayerPiece($square);
    }

    public function makeMove($from, $to): void
    {
        $position = $this->fenToPosition($this->fen);
        [$fromFile, $fromRank] = str_split($from);
        [$toFile, $toRank] = str_split($to);

        $fromFileIndex = ord($fromFile) - ord('a');
        $fromRankIndex = 8 - intval($fromRank);
        $toFileIndex = ord($toFile) - ord('a');
        $toRankIndex = 8 - intval($toRank);

        $piece = $position[$fromRankIndex][$fromFileIndex];
        $capturedPiece = $position[$toRankIndex][$toFileIndex];

        // Record captured piece
        if ($capturedPiece !== ' ') {
            $side = ctype_upper($piece) ? 'white' : 'black';
            $this->capturedPieces[$side][] = $capturedPiece;
        }

        // Handle pawn promotion
        if (strtolower($piece) === 'p') {
            $targetRank = ctype_upper($piece) ? $this->promotionRanks['white'] : $this->promotionRanks['black'];
            if (8 - $toRankIndex === $targetRank) {
                $piece = ctype_upper($piece) ? 'M' : 'm'; // Promote to เม็ด
            }
        }

        // Update position
        $position[$toRankIndex][$toFileIndex] = $piece;
        $position[$fromRankIndex][$fromFileIndex] = ' ';

        // Update FEN and game state
        $this->fen = $this->positionToFen($position);
        $this->lastMove = [$from, $to];
        $this->moveCount++;

        // ตรวจสอบการรุกและรุกจน
        $this->isCheck = $this->isInCheck();
        $this->isCheckmate = $this->isCheck && $this->isCheckmate();

        // Dispatch events
        $this->dispatchBoardEvents($capturedPiece !== ' ');
    }

    protected function fenToPosition(string $fen): array
    {
        $position = array_fill(0, 8, array_fill(0, 8, ' '));
        $ranks = explode('/', explode(' ', $fen)[0]);

        foreach ($ranks as $rankIndex => $rank) {
            $fileIndex = 0;
            for ($i = 0; $i < strlen($rank); $i++) {
                $char = $rank[$i];
                if (is_numeric($char)) {
                    $fileIndex += intval($char);
                } else {
                    $position[$rankIndex][$fileIndex] = $char;
                    $fileIndex++;
                }
            }
        }

        return $position;
    }

    protected function positionToFen(array $position): string
    {
        $fen = '';
        foreach ($position as $rank) {
            $emptyCount = 0;
            foreach ($rank as $piece) {
                if ($piece === ' ') {
                    $emptyCount++;
                } else {
                    if ($emptyCount > 0) {
                        $fen .= $emptyCount;
                        $emptyCount = 0;
                    }
                    $fen .= $piece;
                }
            }
            if ($emptyCount > 0) {
                $fen .= $emptyCount;
            }
            $fen .= '/';
        }

        $fen = rtrim($fen, '/');

        // สลับผู้เล่น
        $currentPlayer = strpos($this->fen, 'w') !== false ? 'b' : 'w';
        $fen .= " $currentPlayer - - 0 1";

        return $fen;
    }

    public function getPieceOnSquare($square)
    {
        if (!$this->isPieceOnSquare($square)) {
            return null;
        }

        $position = $this->fenToPosition($this->fen);
        [$file, $rank] = str_split($square);
        $fileIndex = ord($file) - ord('a');
        $rankIndex = 8 - intval($rank);
        return $position[$rankIndex][$fileIndex];
    }

    public function render()
    {
        return view('livewire-makrukboard::board', [
            'squares' => $this->getSquareClassesProperty(),
            'coordinates' => $this->showCoordinates ? $this->getCoordinates() : [],
            'pieceValues' => $this->showPieceValues ? $this->pieceValues : [],
            'animationDuration' => $this->animationSpeed . 'ms',
        ]);
    }
}
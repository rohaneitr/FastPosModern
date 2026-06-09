<?php

namespace App\Modules\Sales\Services;

class POSReceiptEngine
{
    // ESC/POS Command Hex Codes
    public const ESC = "\x1b";
    public const GS = "\x1d";
    
    public const INIT_PRINTER = self::ESC . "\x40";
    public const UTF8_CHARSET = self::ESC . "\x74\x11"; // Often \x1b\x74\x11 enables WPC1252 or similar, specific to model. In modern models, setting character set.
    
    public const ALIGN_CENTER = self::ESC . "\x61\x01";
    public const ALIGN_LEFT = self::ESC . "\x61\x00";
    public const ALIGN_RIGHT = self::ESC . "\x61\x02";
    
    public const FONT_BOLD_ON = self::ESC . "\x45\x01";
    public const FONT_BOLD_OFF = self::ESC . "\x45\x00";

    public const TEXT_DOUBLE_HEIGHT = self::GS . "\x21\x01";
    public const TEXT_DOUBLE_WIDTH = self::GS . "\x21\x10";
    public const TEXT_NORMAL = self::GS . "\x21\x00";

    public const CASH_DRAWER_KICK = self::ESC . "\x70\x00\x19\xfa";
    public const PAPER_CUT_FULL = self::GS . "\x56\x41\x00";
    public const PAPER_CUT_PARTIAL = self::GS . "\x56\x42\x00";

    protected int $paperWidth = 42; // Standard 80mm thermal paper column count
    protected int $colDesc = 22;
    protected int $colQty = 8;
    protected int $colTotal = 12;

    public function generateReceiptStream(array $transactionData, array $businessData): string
    {
        // 1. Hardware Initialization & Charset
        $stream = self::INIT_PRINTER . self::UTF8_CHARSET;

        // 2. Header Block
        $stream .= self::ALIGN_CENTER . self::FONT_BOLD_ON;
        $stream .= $businessData['name'] . "\n";
        $stream .= self::FONT_BOLD_OFF . self::TEXT_NORMAL;
        $stream .= $businessData['address'] ?? '' . "\n";
        $stream .= "Tel: " . ($businessData['phone'] ?? '') . "\n";
        $stream .= str_repeat('-', $this->paperWidth) . "\n";

        $stream .= self::ALIGN_LEFT;
        $stream .= "Receipt #: " . $transactionData['id'] . "\n";
        $stream .= "Date: " . date('Y-m-d H:i:s', strtotime($transactionData['created_at'])) . "\n";
        $stream .= str_repeat('-', $this->paperWidth) . "\n";

        // 3. Grid Header
        // 22 chars left aligned | 8 chars right aligned | 12 chars right aligned
        $headerLine = $this->mbStrPad("Item", $this->colDesc, ' ', STR_PAD_RIGHT) .
                      $this->mbStrPad("Qty/VAT", $this->colQty, ' ', STR_PAD_LEFT) .
                      $this->mbStrPad("Total", $this->colTotal, ' ', STR_PAD_LEFT);
        
        $stream .= self::FONT_BOLD_ON . $headerLine . "\n" . self::FONT_BOLD_OFF;
        $stream .= str_repeat('-', $this->paperWidth) . "\n";

        // 4. Line Items with Word-Wrap Defense
        foreach ($transactionData['items'] as $item) {
            $name = $item['name'];
            $qtyVat = $item['quantity'] . '/' . floatval($item['tax_rate']) . '%';
            $total = number_format($item['subtotal'] + $item['tax_amount'], 2);

            // Defensive Grid Formatter
            $nameLines = $this->wordWrapMultiByte($name, $this->colDesc);

            // Render first line with Qty and Total
            $firstLineName = array_shift($nameLines);
            $lineStr = $this->mbStrPad($firstLineName, $this->colDesc, ' ', STR_PAD_RIGHT) .
                       $this->mbStrPad($qtyVat, $this->colQty, ' ', STR_PAD_LEFT) .
                       $this->mbStrPad($total, $this->colTotal, ' ', STR_PAD_LEFT);
            $stream .= $lineStr . "\n";

            // Render remaining wrapped lines natively without breaking the columns
            foreach ($nameLines as $subLine) {
                $stream .= $this->mbStrPad($subLine, $this->colDesc, ' ', STR_PAD_RIGHT) . "\n";
            }
        }

        $stream .= str_repeat('-', $this->paperWidth) . "\n";

        // 5. Totals
        $subtotalStr = "Subtotal: " . number_format($transactionData['total_amount'] - $transactionData['tax_amount'], 2);
        $taxStr = "Tax/VAT: " . number_format($transactionData['tax_amount'], 2);
        $grandStr = "TOTAL: " . number_format($transactionData['total_amount'], 2);

        $stream .= self::ALIGN_RIGHT;
        $stream .= $subtotalStr . "\n";
        $stream .= $taxStr . "\n";
        $stream .= self::TEXT_DOUBLE_HEIGHT . self::FONT_BOLD_ON . $grandStr . "\n" . self::TEXT_NORMAL . self::FONT_BOLD_OFF;

        // 6. Footer & Hardware Kick
        $stream .= "\n" . self::ALIGN_CENTER . "Thank you for shopping with us!\n\n\n\n";
        $stream .= self::CASH_DRAWER_KICK . self::PAPER_CUT_FULL;

        return $stream;
    }

    /**
     * Multibyte-aware string padding utility protecting right-alignment.
     */
    protected function mbStrPad(string $input, int $padLength, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
    {
        $diff = strlen($input) - mb_strwidth($input, 'UTF-8');
        return str_pad($input, $padLength + $diff, $padString, $padType);
    }

    /**
     * Multibyte-aware word wrapping. Splits string into an array of strictly bound chunks.
     */
    protected function wordWrapMultiByte(string $string, int $width): array
    {
        $lines = [];
        $currentLine = '';

        $words = preg_split('/[\s]+/', $string);
        foreach ($words as $word) {
            $wordWidth = mb_strwidth($word, 'UTF-8');
            $lineWidth = mb_strwidth($currentLine, 'UTF-8');

            if ($lineWidth + $wordWidth + 1 <= $width) {
                $currentLine .= (empty($currentLine) ? '' : ' ') . $word;
            } else {
                if (!empty($currentLine)) {
                    $lines[] = $currentLine;
                }
                
                // If a single word is longer than the column, hard-split it
                if ($wordWidth > $width) {
                    while (mb_strwidth($word, 'UTF-8') > $width) {
                        $chunk = mb_strimwidth($word, 0, $width, '', 'UTF-8');
                        $lines[] = $chunk;
                        $word = mb_substr($word, mb_strlen($chunk, 'UTF-8'), null, 'UTF-8');
                    }
                }
                $currentLine = $word;
            }
        }
        
        if (!empty($currentLine)) {
            $lines[] = $currentLine;
        }

        return empty($lines) ? [''] : $lines;
    }
}

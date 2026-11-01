<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;

class Button extends Component
{
    /**
     * @var 'primary'|'secondary'|'info'|'success'|'danger'|'warning'|'alert'
     */
    public $variant;

    /**
     * @var bool
     */
    public $outline;

    public function __construct(
        public $type = null,
        public $href = '#',
        public $icon = null,
        public $iconPosition = 'left',
        public $size = 'md',
        $variant = 'primary',
        $outline = false,
        // Legacy boolean props
        $primary = false,
        $secondary = false,
        $info = false,
        $alert = false,
        $success = false,
        $danger = false,
        $warning = false,
        $primaryOutline = false,
        $secondaryOutline = false,
        $infoOutline = false,
        $successOutline = false,
        $alertOutline = false,
        $dangerOutline = false,
        $warningOutline = false
    ) {
        // Determine outline
        $this->outline = $outline ||
            $primaryOutline || $secondaryOutline || $infoOutline ||
            $successOutline || $dangerOutline || $warningOutline || $alertOutline;

        // Determine variant
        if ($primary || $primaryOutline) {
            $this->variant = 'primary';
        } elseif ($secondary || $secondaryOutline) {
            $this->variant = 'secondary';
        } elseif ($info || $infoOutline) {
            $this->variant = 'info';
        } elseif ($success || $successOutline) {
            $this->variant = 'success';
        } elseif ($danger || $dangerOutline) {
            $this->variant = 'danger';
        } elseif ($warning || $warningOutline) {
            $this->variant = 'warning';
        } elseif ($alert || $alertOutline) {
            $this->variant = 'alert';
        } else {
            $this->variant = $variant;
        }
    }

    public function getClasses(): string
    {
        $sizeClasses = [
            'xs' => 'px-2.5 py-1.5 text-xs',
            'sm' => 'px-3 py-2 text-sm',
            'md' => 'px-4 py-2 text-sm',
            'lg' => 'px-4 py-2 text-base',
            'xl' => 'px-6 py-3 text-base',
        ];

        // Sparkle theme: one solid primary, a quiet white secondary, soft tinted info/warning, solid status colours.
        $variantClasses = [
            'primary' => $this->outline
                ? 'border-primary-600 text-primary-600 hover:bg-primary-50 dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-900/20'
                : 'bg-primary-600 text-white shadow-sm shadow-primary-600/30 hover:bg-primary-700 focus:ring-primary-500/40',
            'secondary' => $this->outline
                ? 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800'
                : 'bg-white text-gray-700 !border-gray-200 hover:!border-primary-300 hover:text-gray-900 dark:bg-gray-900 dark:text-gray-200 dark:!border-gray-700 focus:ring-primary-500/30',
            'info' => $this->outline
                ? 'border-primary-500 text-primary-600 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-900/20'
                : 'bg-primary-50 text-primary-700 hover:bg-primary-100 dark:bg-primary-900/40 dark:text-primary-200 dark:hover:bg-primary-900/60 focus:ring-primary-500/30',
            'success' => $this->outline
                ? 'border-[#1baf7a] text-[#0f8a5f] hover:bg-[#1baf7a]/10 dark:text-[#5fd3a8]'
                : 'bg-[#1baf7a] text-white hover:bg-[#169a6b] focus:ring-[#1baf7a]/40',
            'danger' => $this->outline
                ? 'border-[#d03b3b] text-[#b02f2f] hover:bg-[#d03b3b]/10 dark:text-[#f08a8a]'
                : 'bg-[#d03b3b] text-white hover:bg-[#b83232] focus:ring-[#d03b3b]/40',
            'warning' => $this->outline
                ? 'border-[#eb6834] text-[#c24e1d] hover:bg-[#eb6834]/10 dark:text-[#f2a07e]'
                : 'bg-[#eb6834]/12 text-[#c24e1d] hover:bg-[#eb6834]/20 dark:text-[#f2a07e] focus:ring-[#eb6834]/30',
            'alert' => $this->outline
                ? 'border-[#eb6834] text-[#c24e1d] hover:bg-[#eb6834]/10 dark:text-[#f2a07e]'
                : 'bg-[#eb6834] text-white hover:bg-[#d65a28] focus:ring-[#eb6834]/40',
        ];

        $baseClasses = 'inline-flex items-center justify-center gap-1.5 font-semibold rounded-lg transition-colors duration-150 active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-offset-0 disabled:opacity-50 disabled:cursor-not-allowed';
        $borderClass = $this->outline ? 'border bg-transparent' : 'border border-transparent';

        return $baseClasses . ' ' . $borderClass . ' ' .
               ($sizeClasses[$this->size] ?? $sizeClasses['md']) . ' ' .
               ($variantClasses[$this->variant] ?? $variantClasses['primary']);
    }

    public function render()
    {
        return view('components.button');
    }
}

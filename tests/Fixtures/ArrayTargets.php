<?php

namespace NickDeKruijk\BotNoise\Tests\Fixtures;

use Livewire\Component;

class ArrayTargets extends Component
{
    public $untypedEmpty;

    public $untypedScalar = '';

    public string $typedScalar = '';

    public ?string $nullableScalar = null;

    public array $typedArray = [];

    public ?array $nullableArray = null;

    public $untypedArray = [];

    public $filledInMount;

    public int|array $union = 0;

    public mixed $anything = null;

    public array $form = ['tags' => []];

    public function mount(): void
    {
        $this->filledInMount = ['a'];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    public function rendering(View $view): void
    {
        $view->layout('layouts.app');
    }
}; ?>

<section class="w-full">
    TEST
</section>

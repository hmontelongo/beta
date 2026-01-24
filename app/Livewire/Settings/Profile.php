<?php

namespace App\Livewire\Settings;

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $email = '';

    // Avatar uses attribute validation for real-time feedback
    #[Validate('nullable|image|max:2048')]
    public $avatar = null;

    // Agent-specific fields (validated inline in updateProfileInformation)
    public string $phone = '';

    public string $whatsapp = '';

    public string $businessName = '';

    public string $tagline = '';

    public string $brandColor = '';

    public string $defaultWhatsappMessage = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = $user->name;
        $this->email = $user->email;

        if ($user->isAgent()) {
            $this->phone = $user->phone ?? '';
            $this->whatsapp = $user->whatsapp ?? '';
            $this->businessName = $user->business_name ?? '';
            $this->tagline = $user->tagline ?? '';
            $this->brandColor = $user->brand_color ?? '';
            $this->defaultWhatsappMessage = $user->default_whatsapp_message ?? '';
        }
    }

    public function updatedAvatar(): void
    {
        $this->validateOnly('avatar');
    }

    public function saveAvatar(): void
    {
        $this->validate(['avatar' => 'required|image|max:2048']);

        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $this->avatar->store("avatars/{$user->id}", 'public');

        $user->update(['avatar_path' => $path]);

        $this->avatar = null;

        Flux::toast(
            text: __('Profile photo updated'),
            variant: 'success',
        );
    }

    public function deleteAvatar(): void
    {
        $user = auth()->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);

            Flux::toast(
                text: __('Profile photo removed'),
                variant: 'success',
            );
        }
    }

    public function updateProfileInformation(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($user->isAgent()) {
            $agentValidated = $this->validate([
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'businessName' => 'nullable|string|max:100',
                'tagline' => 'nullable|string|max:150',
                'brandColor' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'defaultWhatsappMessage' => 'nullable|string|max:500',
            ]);

            $user->phone = $agentValidated['phone'] ?: null;
            $user->whatsapp = $agentValidated['whatsapp'] ?: null;
            $user->business_name = $agentValidated['businessName'] ?: null;
            $user->tagline = $agentValidated['tagline'] ?: null;
            $user->brand_color = $agentValidated['brandColor'] ?: null;
            $user->default_whatsapp_message = $agentValidated['defaultWhatsappMessage'] ?: null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function resendVerificationNotification(): void
    {
        $user = auth()->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function render(): View
    {
        return view('livewire.settings.profile', [
            'user' => auth()->user(),
        ]);
    }
}

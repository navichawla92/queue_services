<div class="space-y-5 rounded-2xl bg-white p-6 shadow-sm" data-testid="feedback-form">
    @if ($state === 'thanks')
        <p class="text-center text-xl font-semibold">
            {{ $alreadyUsed ? __("Thanks, we've already received your feedback.") : __('Thank you for your feedback!') }}
        </p>
    @elseif ($state === 'expired')
        <p class="text-center text-xl">{{ __('This feedback link has expired.') }}</p>
    @else
        <h1 class="text-2xl font-bold">{{ __('How did we do?') }}</h1>
        <p class="text-slate-600">{{ $request->location->name }} · {{ $request->ticket->service->name }}</p>
        <form wire:submit="submit" class="space-y-5">
            <fieldset>
                <legend class="font-medium">{{ __('Overall, how satisfied are you?') }}</legend>
                <div class="mt-2 flex gap-2" role="radiogroup">
                    @foreach (range(1, 5) as $n)
                        <label @class(['flex h-14 w-14 cursor-pointer items-center justify-center rounded-xl border-2 text-2xl', 'border-[var(--brand)] bg-[var(--brand)] text-white' => $rating === $n, 'border-slate-200' => $rating !== $n])>
                            <input type="radio" wire:model.live="rating" value="{{ $n }}" class="sr-only" aria-label="{{ trans_choice(':n star|:n stars', $n, ['n' => $n]) }}">★
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ __('1 = very unsatisfied, 5 = very satisfied') }}</p>
                @error('rating') <p class="text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            @foreach ($questions as $i => $question)
                <fieldset>
                    <legend class="font-medium">{{ $question }}</legend>
                    <div class="mt-2 flex gap-2">
                        @foreach (range(1, 5) as $n)
                            <label class="flex items-center gap-1 text-sm"><input type="radio" wire:model="extra.{{ $i }}" value="{{ $n }}"> {{ $n }}</label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <div>
                <label for="fb-comment" class="font-medium">{{ __('Anything else? (optional)') }}</label>
                <textarea id="fb-comment" wire:model="comment" rows="4" maxlength="2000" class="mt-1 w-full rounded-xl border border-slate-300 px-4 py-3"></textarea>
            </div>
            <button type="submit" class="w-full rounded-xl bg-[var(--brand)] px-5 py-3 font-semibold text-white">{{ __('Send feedback') }}</button>
        </form>
    @endif
</div>

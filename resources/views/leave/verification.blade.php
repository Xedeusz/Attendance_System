<x-layouts.app title="Document Verification">
    <x-page-header title="LIMCOMA Document Verification" description="Verify an official leave approval document." />
    @if(auth()->user()->isHr())
        <form class="card mb-5 max-w-2xl" method="GET" action="{{ route('leave.verify.search') }}">
            <div class="card-body">
                <x-input name="reference" label="Document reference" maxlength="100" :value="request()->query('reference')" required />
                <x-button type="submit">Find document</x-button>
            </div>
        </form>
    @endif
    @if($state !== 'search')
        @php
            [$heading, $description] = match ($state) {
                'valid' => ['Valid Approved Record', 'This document reference matches a current approved leave record in the LIMCOMA system.'],
                'revoked' => ['Document Revoked', 'This version is no longer an official current approval record.'],
                'superseded' => ['Previous Document Version', 'A newer official version has been issued.'],
                'unavailable' => ['Verification Unavailable', 'The system could not complete the verification checks.'],
                default => ['Unable to Verify Document', 'No valid official document was found for this verification reference.'],
            };
        @endphp
        <section class="card max-w-2xl" aria-labelledby="verification-result">
            <div class="card-body">
                <div @class(['flex items-center gap-3', 'text-[#087C68]' => $state === 'valid', 'text-amber-900' => $state !== 'valid'])>
                    <x-icon :name="$state === 'valid' ? 'check' : 'info'" />
                    <h2 id="verification-result" class="text-lg font-bold">{{ $heading }}</h2>
                </div>
                <p class="mt-2">{{ $description }}</p>
                @if($document)
                    <dl class="my-4">
                        @foreach([
                            'Document Reference' => $document->document_reference,
                            'Document Type' => 'Leave Application Form',
                            'Form Code' => $document->template_reference,
                            'Approval Status' => ucfirst($document->leave->status),
                            'Document Version' => $document->version,
                            'Issued Date' => $document->generated_at?->timezone(config('attendance.timezone'))->format('M j, Y H:i:s P'),
                            'Supervisor Approver' => $document->approver_name,
                            'Document Integrity' => $integrity,
                        ] as $label => $value)
                            <div class="profile-row"><dt class="muted">{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                    @if($integrity === 'Match' && $state !== 'invalid')
                        <x-button :href="route('leave.verify.original', $document)">View Official PDF</x-button>
                        <p class="mt-3 text-sm text-slate-600">Compare the authoritative PDF retrieved from LIMCOMA with the copy presented to you. A QR scan identifies the issued record.</p>
                    @endif
                @endif
            </div>
        </section>
    @endif
</x-layouts.app>

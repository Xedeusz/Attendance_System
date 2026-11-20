<?php

namespace Database\Seeders;

use App\Models\PrivacyNotice;
use Illuminate\Database\Seeder;

class PrivacyNoticeSeeder extends Seeder
{
    public function run(): void
    {
        PrivacyNotice::firstOrCreate(['version' => 'dpo-review-draft'], [
            'title' => 'LIMCOMA Data Privacy Notice (draft for review)',
            'requires_acknowledgement' => false,
            'effective_at' => now(),
            'content' => <<<'NOTICE'
## Introduction
LIMCOMA Multipurpose Cooperative operates this workspace to administer employment information, attendance, and leave. Draft for authorized privacy and legal review.

## Employee and account information
The workspace maintains employee identifiers, names, work email, optional contact and profile photo, branch, department, job information, and Supervisor assignment for employee administration and account access.

## Attendance and location
Time In and Time Out record attendance dates and times, schedules, exceptions and corrections. The attendance flow uses a live photograph and a device-reported location, including accuracy, to verify the submitted event against an assigned branch geofence. It does not continuously capture location or use facial matching. Authorized HR staff can review evidence and exceptions.

## Leave
Leave records include dates, type, status, remarks, relevant supporting documents, and review history. The authorized Supervisor can review assigned employees' requests. Final approvals may use the Supervisor's electronic signature in an official document.

## Account security and safeguards
Sign-in, role and record-level access checks, protected file retrieval, and audit records support authorized workflows and accountability. Confirm the deployment's actual protections and third-party service recipients before publication.

## Lawful bases and recipients
DPO review required for the basis of each processing activity, including attendance photos, location data, employee recordkeeping and sensitive leave documents. Verify the actual classes of internal and external recipients.

## Retention
DPO review required for the category-specific retention and disposal schedule. Verify operational settings, exceptions, and legal holds before publication.

## Privacy rights and requests
See National Privacy Commission guidance for rights and conditions. Confirm the designated contact, request channel, identity verification and response process before publication.

## Privacy contact
Insert the verified contact details of the designated privacy officer or representative.

## Updates
Published versions show their effective date and remain available in the Privacy & Data Protection center. Acknowledgement records receipt and awareness of the identified version.
NOTICE,
        ]);
    }
}

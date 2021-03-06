<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CashAdvanceBulkRequestApprovalNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $cashAdvances;
    public $tenant;
    public $tenantUrl;
    public $bulkId;
    public $token;
    public $projectName;

    /**
     * Create a new message instance.
     *
     * @param $employeeContractExpired
     * @param $employeeContractExpiredSoon
     */
    public function __construct($cashAdvances, $tenant, $tenantUrl, $bulkId, $token, $projectName)
    {
        $this->cashAdvances = $cashAdvances;
        $this->tenant = $tenant;
        $this->tenantUrl = $tenantUrl;
        $this->bulkId = $bulkId;
        $this->token = $token;
        $this->projectName = $projectName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Approval Email')
                ->view('emails.finance.cash-advance.bulk-request-approval-notification');
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
//use Illuminate\Contracts\Queue\ShouldQueue;

class MailSenderMailable extends Mailable /*implements ShouldQueue*/
{
    use Queueable, SerializesModels;

     public $data = null;
     public $type = null;
     public $subject = null;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($type, $data, $subject)
    {
        $this->data = $data;
        $this->type = $type;
        $this->subject = $subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if($this->type == 'RESET_ACCOUNT'){
            return $this->subject($this->subject)->view('email.reset_account')->with($this->data);
        }
        else if ($this->type == 'STYLE_CREATE') {
            return $this->subject($this->subject)->view('email.email_smv')->with($this->data);
        }
        else if ($this->type == 'SMV_CREATE') {
            return $this->subject($this->subject)->view('email.email_smv_added_to_style')->with($this->data);
        }
        else if ($this->type == 'COSTING_CONSUMPTION_CAD') {
            return $this->subject($this->subject)->view('email.email_smv _consumption_for_style')->with($this->data);
        }
        else if ($this->type == 'COSTING_CONSUMPTION_IE') {
            return $this->subject($this->subject)->view('email.email_smv _consumption_for_style _ie')->with($this->data);
        }
        else if ($this->type == 'COSTING_CONSUMPTION_ADD') {
            return $this->subject($this->subject)->view('email.email_smv _consumption_added_to_costing')->with($this->data);
        }
        else if ($this->type == 'BOM_CONSUMPTION_CAD') {
            return $this->subject($this->subject)->view('email.email_smv _consumption_for_bom')->with($this->data);
        }
        else if ($this->type == 'BOM_CONSUMPTION_ADD') {
            return $this->subject($this->subject)->view('email.email_smv _consumption_added_to_bom')->with($this->data);
        }
        else if ($this->type == 'MATERIALS_AND_ITEMS_REPORT') {
            return $this->subject($this->subject)->view('email.email_new_materials_and_items_generated')->with($this->data);
        }
        else if ($this->type == 'SMV_CREATE_REPORT') {
            return $this->subject($this->subject)->view('email.email_smv_added_to_style_planning_process')->with($this->data);
        }
        /*else if ($this->type == 'COSTING_REVIEW_REPORT') {
            return $this->subject($this->subject)->view('email.email_smv_added_to_style_planning_process')->with($this->data);
        }*/
        else if ($this->type == 'PO_CONFIRM') {
            return $this->subject($this->subject)->view('email.email_po_confirm')->with($this->data);
        }
        else{
            return $this->view('email.email')->with($this->data);
        }
    }
}

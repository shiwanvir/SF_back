<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
//use Illuminate\Contracts\Queue\ShouldQueue;

class ApprovalMailable extends Mailable /*implements ShouldQueue*/
{
    use Queueable, SerializesModels;

    private $data;
    private $process;
    private $email_subject;

    public function __construct($_process, $_data, $_subject)
    {
        $this->data = $_data;
        $this->process = $_process;
        $this->email_subject = $_subject;
    }

    public function build()
    {
      if($this->process == 'COSTING'){
        //return $this->subject($this->email_subject)->view('email.email_approval_costing')->with($this->data);
        return $this->subject($this->email_subject)->view('email.email_costing_for_review_costing_approval')->with($this->data);
      }
      else if($this->process == 'COSTING_CONFIRM'){
        return $this->subject($this->email_subject)->view('email.email_confirm_costing')->with($this->data);
      }
      else if($this->process == 'BOM'){
        return $this->subject($this->email_subject)->view('email.email_bom_approval')->with($this->data);
      }
      else if($this->process == 'BOM_CONFIRM'){
        return $this->subject($this->email_subject)->view('email.email_bom_confirm')->with($this->data);
      }
      else if($this->process == 'ITEM'){
        return $this->subject($this->email_subject)->view('email.email')->with($this->data);
      }
      else if($this->process == 'PO'){
        return $this->subject($this->email_subject)->view('email.email_po_approvel')->with($this->data);
      }
      else if($this->process == 'PO_CONFIRM'){
        return $this->subject($this->email_subject)->view('email.email_po_confirm')->with($this->data);
      }
      else if($this->process == 'PRODUCTION_INCENTIVE'){
        return $this->subject($this->email_subject)->view('email.incentive_email')->with($this->data);
      }
      else if($this->process == 'PRODUCTION_INCENTIVE_CONFIRM'){
        return $this->subject($this->email_subject)->view('email.incentive_email_confirm')->with($this->data);
      }
      else if($this->process == 'PRODUCTION_INCENTIVE_REJECT'){
        return $this->subject($this->email_subject)->view('email.incentive_email_reject')->with($this->data);
      }
      else if($this->process == 'GATE_PASS'){
        return $this->view('email.email')->with($this->data);
        //subject($this->email_subject)->view('email.email')->with($this->data);
      }
      else if($this->process == 'OPERATION_COMPONENT'){
            return $this->subject($this->email_subject)->view('email.email_operation_component_for_review')->with($this->data);
            }
      else if($this->process == 'OPERATION_COMPONENT_CONFIRM'){
              return $this->subject($this->email_subject)->view('email.email_operation_component_confirm')->with($this->data);
            }
      else if($this->process == 'OPERATION_COMPONENT_REJECT'){
          return $this->subject($this->email_subject)->view('email.email_operation_component_reject')->with($this->data);
                  }
      else if($this->process == 'MACHINE_TYPE'){
      return $this->subject($this->email_subject)->view('email.email_machine_type_for_review')->with($this->data);
        }
      else if($this->process == 'MACHINE_TYPE_CONFIRM'){
        return $this->subject($this->email_subject)->view('email.email_machine_type_confirm')->with($this->data);
          }
      else if($this->process == 'MACHINE_TYPE_REJECT'){
          return $this->subject($this->email_subject)->view('email.email_machine_type_reject')->with($this->data);
        }
        else if($this->process == 'OPERATION_SUB_COMPONENT'){
              return $this->subject($this->email_subject)->view('email.email_operation_sub_component_for_review')->with($this->data);
        }
        else if($this->process == 'OPERATION_SUB_COMPONENT_CONFIRM'){
              return $this->subject($this->email_subject)->view('email.email_operation_sub_component_confirm')->with($this->data);
        }
        else if($this->process == 'OPERATION_SUB_COMPONENT_REJECT'){
              return $this->subject($this->email_subject)->view('email.email_operation_sub_component_reject')->with($this->data);
        }
    }
}

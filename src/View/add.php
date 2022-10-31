<?php

/** @var Devanych\View\Renderer $this */

$this->layout('layout.php');
$this->block('title', 'Add User');

?>
<div class="row">
    <div class="col-md-8  col-8 ">
        <div class="card my-8">
            <div class="card-header ">
                <span class="h5 my-1">Add User</span>
            </div>
            <div class="card-body">
                <?php echo $form;?>   
            </div>
        </div>          
    </div>
</div>
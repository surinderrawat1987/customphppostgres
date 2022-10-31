<?php


/** @var Devanych\View\Renderer $this */

$this->layout('layout.php');
$this->block('title', 'View All Users');

?>
<script>
    $(document).ready( function () {
        $('#table').DataTable({
            "processing": true,
            "serverSide": true,
            "bPaginate": false,
            "bInfo" : false,            
            "columns": [
                    { "data": "firstname" },
                    { "data": "lastname" },
                    { "data": "email" },
                    { "data": "created_at" }
                ],
                "columnDefs": [
                    {
                        "targets": [0],
                        "visible": true,
                        "searchable": true
                    },
                    {
                        "targets": [1],
                        "visible": true,
                        "searchable": true
                    },
                    {
                        "targets": [2],
                        "visible": true,
                        "searchable": true,
                        "sortable":false
                    },
                    {
                        "targets": [3],
                        "visible": true,
                        "searchable": false
                    }
                ],
                "ajax": '/',            
        });
    });
</script>

<div class="row">
    <div class="col-md-8  col-8 ">
        <table id="table" class="table my-3 table-bordered">
            <thead>
                <th>FirstName</th>
                <th>LastName</th>
                <th>Email</th>
                <th>Created At</th>
            </thead>
            <tbody>
                <?php /*foreach ($users as $user) { ?>
                    <tr>
                        <td><?= $user['firstname']; ?></td>
                        <td><?= $user['lastname']; ?></td>
                        <td><?= $user['email']; ?></td>
                        <td><?= $user['created_at']; ?></td>
                    </tr>
                <?php }*/ ?>
            </tbody>
        </table>
    </div>
</div>
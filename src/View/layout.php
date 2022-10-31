<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Title</title>
    <link rel="icon" href="images/favicon.ico" type="image/x-icon">

    <link rel="stylesheet" href="/src/View/css/bootstrap.min.css">
    <link rel="stylesheet" href="/src/View/css/font-awesome.css">
    <link rel="stylesheet" href="/src/View/css/style.css">
    <link rel="stylesheet" href="/vendor/pear/html_quickform2/data/quickform.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/jquery.dataTables.min.css">
    <script src="/vendor/pear/html_quickform2/data/js/min/quickform.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <header class="mb-3">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                    <li class="nav-item ">
                        <a class="nav-link active" href="/">Home <span class="sr-only">(current)</span></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/user/add">Add</a>
                    </li>
                    </ul>
                </div>
            </div>    
        </nav>
    </header>
    <section class="sec-block">
        <div class="container">    
            <?=$this->renderBlock('content');?>
        </div>
    </section>

</body>


</html>
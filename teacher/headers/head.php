  <?php 
  // This helps the sidebar to show who is active panel
    $who_is_active = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}/{$_SERVER['REQUEST_URI']}";
    $path_parts = pathinfo($who_is_active);

    $folder = './';
    $filename = basename($path_parts['filename']);

    $filepath = $folder . $filename;
    $extension = pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
    if($extension == "html"){
      header('Location: ../404.html');
    }
    if($extension == "php"){
      header('Location: '.$filepath);
    }
    
    if (file_exists($filepath.'.php') || file_exists($filepath)) {
        //echo "File found: " . $filepath;
    } else {
        $filename = parse_url($filename, PHP_URL_PATH);
    }
    $filename = str_replace("_", " ", $filename);
    //echo $path_parts['filename'];
  ?>

        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <link rel="apple-touch-icon" sizes="76x76" href="<?=$_ENV['PAGE_ICON']?>">
        <link rel="icon" type="image/png" href="<?=$_ENV['PAGE_ICON']?>">
        <title><?=$_ENV['PAGE_HEADER']?></title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>


  



  
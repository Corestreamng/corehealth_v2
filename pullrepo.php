<?php

function pull_laravel_project($repo_url, $folder)
{
    echo "cloning repo to folder: $folder\n";
    exec("git clone $repo_url $folder");
}

function install_dependencies($folder)
{
    echo "Installing composer dependencies\n";
    shell_exec("cd $folder && composer install");
}

function generate_app_key($folder)
{
    echo "generating app key\n";
    shell_exec("cd $folder && php artisan key:generate");
}

function create_database($connection_string, $db_name)
{
    $parsed_connection = parse_connection_string($connection_string);
    $host = $parsed_connection['host'];
    $user = $parsed_connection['user'];
    $password = $parsed_connection['password'];

    $connection = new mysqli($host, $user, $password);
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }

    $sql = "CREATE DATABASE IF NOT EXISTS $db_name";
    if ($connection->query($sql) === TRUE) {
        echo "Database created successfully\n";
    } else {
        echo "Error creating database: " . $connection->error . "\n";
    }

    $connection->close();
}

function import_sql_file($connection_string, $sql_file_path)
{
    $parsed_connection = parse_connection_string($connection_string);
    $host = $parsed_connection['host'];
    $user = $parsed_connection['user'];
    $password = $parsed_connection['password'];
    $database = $parsed_connection['database'];

    exec("mysql -h $host -u $user -p$password $database < $sql_file_path");
}

function parse_connection_string($connection_string)
{
    $options = explode(";", $connection_string);
    $parsed_connection = [];
    foreach ($options as $option) {
        list($key, $value) = explode("=", $option);
        $parsed_connection[trim(strtolower($key))] = trim($value);
    }
    return $parsed_connection;
}

function main()
{
    global $argv;

    $repo_url = $argv[1];
    $folder = $argv[2];
    $db_name = $argv[3];
    $sql_file = $argv[4];
    $connection_string = isset($argv[5]) ? $argv[5] : "server=localhost;user=root;password=your_password;database=your_database";

    create_database($connection_string, $db_name);
    import_sql_file($connection_string, $sql_file);

    pull_laravel_project($repo_url, $folder);
    install_dependencies($folder);
    generate_app_key($folder);

    echo "Laravel project setup complete.\n";
}

main();
echo "hello";

//php pullrepo.php https://github.com/laravel/laravel . test2 _corehealth_db_v2_test.sql "server=localhost;user=admin;password=18781875;database=test2"

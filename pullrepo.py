import argparse
import mysql.connector
import subprocess
import sqlite3
import sys

def pull_laravel_project(repo_url, folder):
    print("cloning repo to folder: "+ folder)
    subprocess.run(["git", "clone", repo_url, folder])

def install_dependencies(folder):
    print("Installing composer dependencies")
    subprocess.run(["cd", folder, "&&", "composer", "install"])

def generate_app_key(folder):
    print("genetating app key")
    subprocess.run(["cd", folder, "&&", "php", "artisan", "key:generate"])

def create_database(connection_string, db_name):
    connection = mysql.connector.connect(**parse_connection_string(connection_string))
    cursor = connection.cursor()
    cursor.execute(f"CREATE DATABASE IF NOT EXISTS {db_name}")
    cursor.close()
    connection.close()

def import_sql_file(connection_string, sql_file_path):
    connection = mysql.connector.connect(**parse_connection_string(connection_string))
    cursor = connection.cursor()
    with open(sql_file_path, 'r') as file:
        sql_script = file.read()
        cursor.execute(sql_script)
    cursor.close()
    connection.commit()
    connection.close()

def parse_connection_string(connection_string):
    options = connection_string.split(";")
    parsed_connection = {}
    for option in options:
        key, value = option.split("=")
        key = key.strip().lower()  # Convert key to lowercase for consistency
        if key == 'server':
            key = 'host'  # Change 'server' to 'host'
        parsed_connection[key] = value.strip()
    return parsed_connection

def main():
    parser = argparse.ArgumentParser(description="Laravel project setup script")
    parser.add_argument("repo_url", type=str, help="GitHub repository URL")
    parser.add_argument("folder", type=str, help="Local folder to clone the repository")
    parser.add_argument("db_name", type=str, help="Database name based on the CLI argument")
    parser.add_argument("sql_file", type=str, help="SQL file to import into the database")
    parser.add_argument("--connection_string", type=str, default="server=localhost;user=root;password=your_password;database=your_database", help="MySQL connection string")

    args = parser.parse_args()

    create_database(args.connection_string, args.db_name)
    import_sql_file(args.connection_string, args.sql_file)

    pull_laravel_project(args.repo_url, args.folder)
    install_dependencies(args.folder)
    generate_app_key(args.folder)

    print("Laravel project setup complete.")

if __name__ == "__main__":
    main()

'''
python pullrepo.py <repo_url> <folder> <db_name> <sql_file> --connection_string 'your_connection_string'
'''

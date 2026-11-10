<?php

class BaseController
{
    protected function view(string $view, array $data = [])
    {
        extract($data);
        require __DIR__ . '/../views/' . $view . '.php';
    }

    protected function redirect(string $path)
    {
        header("Location: $path");
        exit;
    }
}

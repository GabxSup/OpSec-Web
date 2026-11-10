<?php

function auth_required()
{
    if (!isset($_SESSION['user'])) {
        header('Location: /login');
        exit;
    }
}

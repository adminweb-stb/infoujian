<?php
/**
 * Root Redirect
 * This file is only useful for local development environments (like Laragon/XAMPP)
 * to automatically redirect the browser to the /public directory.
 * In production (Nginx), this file is ignored as the root points directly to /public.
 */
header("Location: public/");
exit;

::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
:: Wordpress Bundler - PHP package to prepare production bundles for Wordpress plugins and templates
:: Copyright (C) 2022-present Liqueur de Toile
:: @license  GPL-3.0-or-later (https://www.gnu.org/licenses/gpl-3.0.html)
::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::

@echo off

SET lib=%~dp0

php "%lib%wpbundler" %*

echo.

exit /B %ERRORLEVEL%

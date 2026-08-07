param (
    [Parameter(Mandatory)][string]$RepoName,
    [Parameter(Mandatory)][string]$OrgName,
    [bool]$DryRun = $false
)
$ErrorActionPreference = 'Stop'

Write-Host "Downloading latest javascript-templates..."
Invoke-WebRequest "https://github.com/$OrgName/javascript-templates/archive/refs/heads/main.zip" -OutFile javascript-templates.zip

Write-Host "Extracting the archive..."
Expand-Archive javascript-templates.zip -DestinationPath .

Write-Host "Updating the package directory..."
Move-Item -Path javascript-templates-main/*.mustache -Destination "$RepoName/javascript-templates" -Force

Write-Host "Cleaning up..."
Remove-Item -Recurse -Force javascript-templates.zip, javascript-templates-main

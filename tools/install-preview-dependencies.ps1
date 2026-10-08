param(
    [string]$ProtocolPath = "../TeamSelenyx-BedrockProtocol",
    [string]$DataPath = "../TeamSelenyx-BedrockData"
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path $PSScriptRoot -Parent
Push-Location $projectRoot
$previousComposer = $env:COMPOSER
try {
    $manifest = Get-Content composer.json -Raw | ConvertFrom-Json
    $localRepositories = @()
    foreach ($dependency in @(
        @{ Path = $ProtocolPath; Name = "teamselenyx/bedrock-protocol" },
        @{ Path = $DataPath; Name = "teamselenyx/bedrock-data" }
    )) {
        $resolvedPath = (Resolve-Path -LiteralPath $dependency.Path).Path
        $package = Get-Content (Join-Path $resolvedPath "composer.json") -Raw | ConvertFrom-Json
        if ($package.name -ne $dependency.Name) {
            throw "Unexpected package at ${resolvedPath}: $($package.name)"
        }
        $localRepositories += @{
            type = "path"
            url = $resolvedPath.Replace('\', '/')
            options = @{
                symlink = $false
                reference = "config"
                versions = @{ $dependency.Name = "dev-bedrock-1.26.60" }
            }
        }
    }
    $manifest.repositories = $localRepositories + @($manifest.repositories)
    $json = $manifest | ConvertTo-Json -Depth 32
    [IO.File]::WriteAllText((Join-Path $projectRoot "composer-local-protocol.json"), $json + "`n", [Text.UTF8Encoding]::new($false))
    Copy-Item composer.lock composer-local-protocol.lock -Force
    $env:COMPOSER = "composer-local-protocol.json"
    & ./bin/php/php.exe composer.phar update teamselenyx/bedrock-protocol teamselenyx/bedrock-data --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "Preview dependency installation failed ($LASTEXITCODE)" }
    # Path repository references do not include uncommitted source changes.
    & ./bin/php/php.exe composer.phar reinstall teamselenyx/bedrock-protocol teamselenyx/bedrock-data --no-interaction
    if ($LASTEXITCODE -ne 0) { throw "Preview dependency refresh failed ($LASTEXITCODE)" }
    & ./bin/php/php.exe composer.phar update-codegen
    if ($LASTEXITCODE -ne 0) { throw "Preview code generation failed ($LASTEXITCODE)" }
} finally {
    $env:COMPOSER = $previousComposer
    Pop-Location
}

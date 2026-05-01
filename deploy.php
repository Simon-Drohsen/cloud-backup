<?php

declare(strict_types=1);

/**
 * instride AG
 *
 * LICENSE
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that is distributed with this source code.
 *
 * @copyright 2024 instride AG (https://instride.ch)
 */

namespace Deployer;

import('recipe/symfony.php');

// Hosts
host('10.6.35.104')
    ->setRemoteUser('ubnt')
    ->setPort(22)
    ->setDeployPath('/var/www/cloud-backup')
    ->setIdentityFile('.deployer/id_deployer')
    ->set('branch', 'main')
    ->set('labels', ['stage' => 'prd'])
    ->set('writable_use_sudo', true);


// Configuration
set('repository', 'ssh://git@github.com/simon-drohsen/cloud-backup.git');
set('default_stage', 'prd');
set('keep_releases', 2);
set('ssh_multiplexing', false); // Cygwin doesn't support multiplexing
add('writable_dirs', [
    'var/bundles',
    'var/cache',
    'var/classes',
    'var/config',
    'var/sessions',
    'var/tmp',
    'var/versions',
]);

// Shared files and directories
set('shared_files', [
    '.env.local',
    'config/local/database.yaml',
    'var/admin/custom-logo.image',
    'var/config/admin_system_settings/admin_system_settings.yaml',
    'var/config/system_settings/system_settings.yaml',
    'var/config/web_to_print/web_to_print.yaml',
]);
add('shared_dirs', [
    'public/var',
    'var/admin/user-image',
    'var/application-logger',
    'var/config/portal',
    'var/email',
    'var/recyclebin',
    'var/versions',
]);

set('bin/php', static fn () => '/opt/plesk/php/8.3/bin/php');

set('bin/composer', static function () {
    run('cd {{release_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}');

    return '{{bin/php}} {{release_path}}/composer.phar';
});

desc('This checks the build environment of your static assets.');
//task('webpack:check_build', static function () {
//
//    info('👌 Great! Looks like you\'ve compiled a production build.');
//
//    $json = fetch('{{github_build_env}}?ref={{branch}}', 'get', [
//        'Authorization' => sprintf('Bearer %s', getenv('GITHUB_ACCESS_TOKEN')),
//        'Accept' => 'application/vnd.github.v3.raw',
//    ]);
//    $remoteData = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
//
//    if (empty($remoteData)) {
//        throw error('🚫 You have to push the production build before deploying.');
//    }
//
//    info('✅  Everything is fine, let\'s continue deploying to the server.');
//})->desc('This checks the build environment of your static assets.');
//before('deploy:prepare', 'webpack:check_build');

task('deploy:writable', function () {
    run('sudo chown -R 33:33 {{release_path}}/var');
    run('sudo chown -R 33:33 {{release_path}}/public/var');
    run('chmod -R ug+rwX {{release_path}}/var');
    run('chmod -R ug+rwX {{release_path}}/public/var');
});

desc('Rebuilds Pimcore Classes');
task('pimcore:rebuild_classes', function () {
    run('{{bin/console}} pimcore:deployment:classes-rebuild --create-classes --force --no-interaction');
});

desc('Removes cache');
task('pimcore:cache_clear', function () {
    run('rm -rf {{release_or_current_path}}/var/cache/dev/*');
});

desc('Restarts all worker processes so that they see the newly deployed code.');
task('messenger:stop-workers', static function () {
    run('{{bin/console}} messenger:stop-workers');
});
after('deploy:success', 'messenger:stop-workers');

desc('Deploy Task');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:clear_paths',
    'pimcore:rebuild_classes',
    'pimcore:cache_clear',
    'database:migrate',
    'deploy:publish',
])->desc('Deploys your project');

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');


<?php

use Appwrite\Extend\Exception;
use Appwrite\Utopia\Database\Validator\CustomId;
use Appwrite\Utopia\Database\Validator\Queries\Teams as OrganizationsQueries;
use Appwrite\Utopia\Request;
use Appwrite\Utopia\Response;
use Appwrite\Utopia\Database\Documents\User;
use Utopia\App;
use Utopia\Config\Config;
use Utopia\Database\Database;
use Utopia\Database\DateTime;
use Utopia\Database\Document;
use Utopia\Database\Exception\Duplicate;
use Utopia\Database\Exception\Query as QueryException;
use Utopia\Database\Helpers\ID;
use Utopia\Database\Helpers\Permission;
use Utopia\Database\Helpers\Role;
use Utopia\Database\Query;
use Utopia\Database\Validator\Authorization;
use Utopia\Database\Validator\Queries;
use Utopia\Database\Validator\Query\Cursor;
use Utopia\Database\Validator\Query\Limit;
use Utopia\Database\Validator\Query\Offset;
use Utopia\Validator\ArrayList;
use Utopia\Validator\Boolean;
use Utopia\Validator\Text;
use Utopia\Validator\WhiteList;

App::post('/v1/organizations')
    ->desc('Create organization')
    ->groups(['api', 'organizations'])
    ->label('scope', 'organizations.write')
    ->label('audits.event', 'organization.create')
    ->label('audits.resource', 'organization/{response.$id}')
    ->param('organizationId', '', new CustomId(), 'Organization ID. Choose a custom ID or generate a random ID with `ID.unique()`. Valid chars are a-z, A-Z, 0-9, period, hyphen, and underscore. Can\'t start with a special char. Max length is 36 chars.')
    ->param('name', null, new Text(128), 'Organization name. Max length: 128 chars.')
    ->param('description', '', new Text(256), 'Organization description. Max length: 256 chars.', true)
    ->param('logo', '', new Text(1024), 'Organization logo.', true)
    ->param('url', '', new Text(1024), 'Organization URL.', true)
    ->inject('response')
    ->inject('user')
    ->inject('dbForPlatform')
    ->inject('authorization')
    ->action(function (string $organizationId, string $name, string $description, string $logo, string $url, Response $response, Document $user, Database $dbForPlatform, Authorization $authorization) {

        $isPrivilegedUser = User::isPrivileged($authorization->getRoles());
        $isAppUser = User::isApp($authorization->getRoles());

        $organizationId = $organizationId == 'unique()' ? ID::unique() : $organizationId;

        try {
            $organization = $authorization->skip(fn () => $dbForPlatform->createDocument('organizations', new Document([
                '$id' => $organizationId,
                '$permissions' => [
                    Permission::read(Role::user($user->getId())),
                    Permission::update(Role::user($user->getId())),
                    Permission::delete(Role::user($user->getId())),
                ],
                'name' => $name,
                'description' => $description,
                'logo' => $logo,
                'url' => $url,
                'accessedAt' => DateTime::now(),
                'search' => implode(' ', [$organizationId, $name, $description]),
            ])));
        } catch (Duplicate $th) {
            throw new Exception(Exception::GENERAL_DUPLICATE, 'Organization already exists');
        }

        $response
            ->setStatusCode(Response::STATUS_CODE_CREATED)
            ->dynamic($organization, Response::MODEL_DOCUMENT);
    });

App::get('/v1/organizations')
    ->desc('List organizations')
    ->groups(['api', 'organizations'])
    ->label('scope', 'organizations.read')
    ->param('queries', [], new OrganizationsQueries(), 'Array of query strings generated using the Query class provided by the SDK. Maximum of ' . APP_LIMIT_ARRAY_PARAMS_SIZE . ' queries are allowed.', true)
    ->param('search', '', new Text(256), 'Search term to filter your list results. Max length: 256 chars.', true)
    ->param('total', true, new Boolean(true), 'When set to false, the total count returned will be 0 and will not be calculated.', true)
    ->inject('response')
    ->inject('dbForPlatform')
    ->inject('user')
    ->action(function (array $queries, string $search, bool $includeTotal, Response $response, Database $dbForPlatform, Document $user) {

        try {
            $queries = Query::parseQueries($queries);
        } catch (QueryException $e) {
            throw new Exception(Exception::GENERAL_QUERY_INVALID, $e->getMessage());
        }

        if (!empty($search)) {
            $queries[] = Query::search('search', $search);
        }

        // Filter by user's organizations
        $queries[] = Query::equal('$permissions.read', [Role::user($user->getId())->toString()]);

        $cursor = \array_filter($queries, function ($query) {
            return \in_array($query->getMethod(), [Query::TYPE_CURSOR_AFTER, Query::TYPE_CURSOR_BEFORE]);
        });
        $cursor = reset($cursor);
        if ($cursor) {
            /** @var Query $cursor */
            $organizationId = $cursor->getValue();
            $cursorDocument = $dbForPlatform->getDocument('organizations', $organizationId);

            if ($cursorDocument->isEmpty()) {
                throw new Exception(Exception::GENERAL_CURSOR_NOT_FOUND, "Organization '{$organizationId}' for the 'cursor' value not found.");
            }

            $cursor->setValue($cursorDocument);
        }

        $response
            ->dynamic($dbForPlatform->find('organizations', $queries), Response::MODEL_DOCUMENT_LIST);
    });

App::get('/v1/organizations/:organizationId')
    ->desc('Get organization')
    ->groups(['api', 'organizations'])
    ->label('scope', 'organizations.read')
    ->param('organizationId', '', new CustomId(), 'Organization ID.')
    ->inject('response')
    ->inject('dbForPlatform')
    ->action(function (string $organizationId, Response $response, Database $dbForPlatform) {

        $organization = $dbForPlatform->getDocument('organizations', $organizationId);

        if ($organization->isEmpty()) {
            throw new Exception(Exception::GENERAL_NOT_FOUND, 'Organization not found');
        }

        $response->dynamic($organization, Response::MODEL_DOCUMENT);
    });

App::put('/v1/organizations/:organizationId')
    ->desc('Update organization')
    ->groups(['api', 'organizations'])
    ->label('scope', 'organizations.write')
    ->label('audits.event', 'organization.update')
    ->label('audits.resource', 'organization/{request.organizationId}')
    ->param('organizationId', '', new CustomId(), 'Organization ID.')
    ->param('name', null, new Text(128), 'Organization name. Max length: 128 chars.', true)
    ->param('description', '', new Text(256), 'Organization description. Max length: 256 chars.', true)
    ->param('logo', '', new Text(1024), 'Organization logo.', true)
    ->param('url', '', new Text(1024), 'Organization URL.', true)
    ->inject('response')
    ->inject('dbForPlatform')
    ->action(function (string $organizationId, ?string $name, ?string $description, ?string $logo, ?string $url, Response $response, Database $dbForPlatform) {

        $organization = $dbForPlatform->getDocument('organizations', $organizationId);

        if ($organization->isEmpty()) {
            throw new Exception(Exception::GENERAL_NOT_FOUND, 'Organization not found');
        }

        if ($name !== null) {
            $organization->setAttribute('name', $name);
        }
        if ($description !== null) {
            $organization->setAttribute('description', $description);
        }
        if ($logo !== null) {
            $organization->setAttribute('logo', $logo);
        }
        if ($url !== null) {
            $organization->setAttribute('url', $url);
        }

        $organization->setAttribute('accessedAt', DateTime::now());
        $organization->setAttribute('search', implode(' ', [$organizationId, $organization->getAttribute('name'), $organization->getAttribute('description')]));

        $organization = $dbForPlatform->updateDocument('organizations', $organizationId, $organization);

        $response->dynamic($organization, Response::MODEL_DOCUMENT);
    });

App::delete('/v1/organizations/:organizationId')
    ->desc('Delete organization')
    ->groups(['api', 'organizations'])
    ->label('scope', 'organizations.write')
    ->label('audits.event', 'organization.delete')
    ->label('audits.resource', 'organization/{request.organizationId}')
    ->param('organizationId', '', new CustomId(), 'Organization ID.')
    ->inject('response')
    ->inject('dbForPlatform')
    ->action(function (string $organizationId, Response $response, Database $dbForPlatform) {

        $organization = $dbForPlatform->getDocument('organizations', $organizationId);

        if ($organization->isEmpty()) {
            throw new Exception(Exception::GENERAL_NOT_FOUND, 'Organization not found');
        }

        // Check if organization has projects
        $projects = $dbForPlatform->find('projects', [
            Query::equal('organizationId', [$organizationId]),
            Query::limit(1)
        ]);

        if (!empty($projects)) {
            throw new Exception(Exception::GENERAL_ARGUMENT_INVALID, 'Cannot delete organization with existing projects');
        }

        $dbForPlatform->deleteDocument('organizations', $organizationId);

        $response->noContent();
    });

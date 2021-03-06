<?php
/**
 * BileMo Project.
 *
 * (c) 2020.  BigBoss Walid <bigboss@it-bigboss.de>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Swagger;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class SwaggerDecorator implements NormalizerInterface
{
    private $decorated;
    /**
     * @var array
     */
    private $docs;

    const UNUSED_PATHS = [['path' => '/api/categories'], ['path' => '/api/categories/{id}'], ['path' => '/api/customers'],
        ['path' => '/api/customers/{id}'], ['path' => '/api/images'], ['path' => '/api/images/{id}'], ['path' => '/api/users/{id}',
            'method' => 'put', ], ['path' => '/api/products', 'method' => 'post'], ['path' => '/api/products/{id}', 'method' => 'put'],
        ['path' => '/api/products/{id}', 'method' => 'delete'], ];
    const UNUSED_SCHEMAS = ['Customer:jsonld', 'Customer', 'Customer:jsonld-user_post', 'Category', 'Category-categories_read', 'Category:jsonld', 'Category:jsonld-categories_read', 'Image', 'Image:jsonld'];

    public function __construct(NormalizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /***
     * {@inheritdoc }
     */
    public function normalize($object, $format = null, array $context = []): array
    {
        $defaultsDocs = $this->decorated->normalize($object, $format, $context);

        // Disable unused endpoints & their schemas from api-docs.
        $this->unsetElements($defaultsDocs);

        // Remove customer input from api docs
        unset($defaultsDocs['components']['schemas']['User:jsonld-user_post']['properties']['customer']);
        // Add Error to POST api/users
        $defaultsDocs['paths']['/api/users']['post']['responses'][415] = [
            'description' => 'The Body is not a valid json format <br/> The Body is empty',
        ];
        //Add specific description
        $defaultsDocs = $this->getDescription($defaultsDocs);
        //Create Json web token endpoint
        $this->docs = $this->retrieveJwt($defaultsDocs);

        return array_merge_recursive($this->docs, $defaultsDocs);
    }

    /***
     * {@inheritdoc }
     */
    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    private function unsetElements(array &$docs): void
    {
        foreach (self::UNUSED_SCHEMAS as $schema) {
            unset($docs['components']['schemas'][$schema]);
        }
        foreach (self::UNUSED_PATHS as $path) {
            if (!empty($path['method'])) {
                unset($docs['paths'][$path['path']][$path['method']]);
            } else {
                unset($docs['paths'][$path['path']]);
            }
        }
    }

    /**
     * @param $docs
     *
     *@return array
     */
    private function retrieveJwt(array $docs): array
    {
        $docs['components']['schemas']['Token'] = [
            'type' => 'object',
            'properties' => [
                'token' => [
                    'type' => 'string',
                    'readOnly' => true,
                ],
            ],
        ];

        $docs['components']['schemas']['Credentials'] = [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'example' => 'your_user_name',
                ],
                'password' => [
                    'type' => 'string',
                    'example' => 'your_password',
                ],
            ],
        ];

        return [
            'paths' => [
                '/api/login' => [
                    'post' => [
                        'tags' => ['Authenticate'],
                        'operationId' => 'postCredentialsItem',
                        'summary' => 'Get JWT token to login.',
                        'requestBody' => [
                            'description' => 'Create new JWT Token',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Credentials',
                                    ],
                                ],
                            ],
                        ],
                        'responses' => [
                            Response::HTTP_OK => [
                                'description' => 'Get JWT token',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/Token',
                                        ],
                                    ],
                                ],
                            ],
                            Response::HTTP_BAD_REQUEST => [
                                'description' => 'Invalid JSON.',
                            ],
                            Response::HTTP_UNAUTHORIZED => [
                                'description' => 'Invalid credentials.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param $docs
     *
     *@return array
     */
    private function getDescription(array $docs): array
    {
        $docs['info']['description'] = '<h2>Description:</h2>
                <p>BileMo is a company offering a variety of premium mobile phones. through our API you can get the list of your users or our product, and of course you will be also able to add user or delete users.</p>
            <h2>Authentication:</h2>
                <p>To use our API you will need a token so that we can identify you in all the requests. All requests to our API need a <strong>Bearer Token</strong> for authorization.</p>
            <h4>How to generate a token?</h4>
            <p>You will need to generate a token for your Handy user. The customer must have the company role_user in order to have full permission to all the endpoints. You must log in to the web portal: 
                <ul>
                    <li>Go to the <strong><a href="#operations-tag-Authenticate" >endpoint Authenticate</strong></a></li>
                    <li>Click on <strong>try it out</strong></li>
                    <li>Add your username and password</li>
                    <li>Then, you just have to click on <strong>execute</strong>, and copy your Token</li>
                </ul>Your token will expire in th next 3 hours. 
            </p>
            <h4>How to use the token?</h4>
            <p>You need to click on the button <strong>Authorize</strong> and add <strong>Bearer TOKEN</strong> in the field value of the header, where you replace TOKEN with your API token</p>';

        return $docs;
    }
}

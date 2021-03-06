<?php

/**
 * File containing the WebController class
 *
 * @author carlos <crevillo@gmail.com>
 */

namespace Sillonbol\WebBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use Symfony\Component\HttpFoundation\Response;
use eZ\Publish\Core\Pagination\Pagerfanta\ContentSearchAdapter;
use Pagerfanta\Pagerfanta;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\API\Repository\Values\Content\Search\SearchResult;

class WebController extends Controller
{

    /**
     * Renders the social links menu, with cache control
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function socialLinksAction() {
        $rootLocationId = $this->getConfigResolver()->getParameter('content.tree_root.location_id');

        // Setting HTTP cache for the response to be public and with a TTL of 1 day.
        $response = new Response;
        $response->setPublic();
        $response->setSharedMaxAge(86400);
        // Menu will expire when top location cache expires.
        $response->headers->set('X-Location-Id', $rootLocationId);

        $fb_url = $this->container->getParameter('sillonbol.sociallinks.facebook');
        $tw_url = $this->container->getParameter('sillonbol.sociallinks.twitter');
        $about_us = $this->getRepository()->getLocationService()->loadLocation(
                $this->container->getParameter('sillonbol.sociallinks.about_us.location_id')
        );
        $contact = $this->getRepository()->getLocationService()->loadLocation(
                $this->container->getParameter('sillonbol.sociallinks.contact.location_id')
        );

        return $this->render(
                        'SillonbolWebBundle:parts:social.html.twig', array(
                    'fb_url' => $fb_url,
                    'tw_url' => $tw_url,
                    'about_us' => $about_us,
                    'contact' => $contact
                        ), $response
        );
    }

    /**
     * Renders the main menu, with cache control
     *
     * @param int selected id of the location marked as active in the menu
     * 
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function mainMenuAction($selected = 0) {
        $rootLocationId = $this->getConfigResolver()->getParameter('content.tree_root.location_id');

        // Setting HTTP cache for the response to be public and with a TTL of 1 day.
        $response = new Response;
        $response->setPublic();
        $response->setSharedMaxAge(86400);
        // Menu will expire when top location cache expires.
        $response->headers->set('X-Location-Id', $rootLocationId);

        $includeCriterion = $this->get('sillonbol.criteria_helper')
                ->generateContentTypeIncludeCriterion(
                // Get contentType identifiers we want to include from configuration (see default_settings.yml).
                $this->container->getParameter('sillonbol.top_menu.content_types_include')
        );

        $contentList = $this->get('sillonbol.menu_helper')->getTopMenuContent( $rootLocationId, $includeCriterion );

        $locationList = array();
        // Looping against search results to build $locationList
        // Both arrays will be indexed by contentId so that we can easily refer to an element in a list from another element in the other list
        // See page_topmenu.html.twig
        foreach ($contentList as $contentId => $content) {
            $locationList[$contentId] = $this->getRepository()->getLocationService()->loadLocation($content->contentInfo->mainLocationId);
        }

        return $this->render(
            'SillonbolWebBundle:parts:mainmenu.html.twig',
            array(
               'locationList' => $locationList,
               'contentList' => $contentList,
               'selected' => $selected
            ),
            $response
        );
    }

    /**
     * Renders article with extra parameters that controls page elements visibility such as image and summary
     *
     * @param $locationId
     * @param $viewType
     * @param bool $layout
     * @param array $params
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showArticleAction($locationId, $viewType, $layout = false, array $params = array()) {
        $location = $this->getRepository()->getLocationService()->loadLocation( $locationId );
        $path_array = explode( '/', $location->pathString );
        $category = $this->getRepository()->getLocationService()->loadLocation( $path_array[3] );

        return $this->get('ez_content')->viewLocation(
             $locationId,
             $viewType,
             $layout,
             array(
                'category' => $category,
                'published_rcf822' => $location->getContentInfo()->publishedDate->format(\DateTime::RFC822)
             )
        );
    }

    /**
     * Displays the list of category post
     * Note: This is a fully customized controller action, it will generate the response and call
     *       the view. Since it is not calling the ViewControler we don't need to match a specific
     *       method signature.
     *
     * @param int $locationId of a category
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function categoryListAction( $locationId )
    {
        $response = new Response();

        // Setting default cache configuration (you can override it in you siteaccess config)
        $response->setSharedMaxAge( $this->getConfigResolver()->getParameter( 'content.default_ttl' ) );

        // Make the response location cache aware for the reverse proxy
        $response->headers->set( 'X-Location-Id', $locationId );

        // Getting location and content from ezpublish dedicated services
        $repository = $this->getRepository();
        $location = $repository->getLocationService()->loadLocation( $locationId );
        $content = $repository
            ->getContentService()
            ->loadContentByContentInfo( $location->getContentInfo() );

        // Using the criteria helper (a demobundle custom service) to generate our query's criteria.
        // This is a good practice in order to have less code in your controller.
        $criteria = array();
        $criteria[] = new Criterion\Visibility( Criterion\Visibility::VISIBLE );
        $criteria[] = new Criterion\Subtree( $location->pathString );
        $criteria[] = new Criterion\ContentTypeIdentifier( 'article' );

        // Generating query
        $query = new Query();
        $query->criterion = new Criterion\LogicalAnd( $criteria );
        $query->sortClauses = array(
            new SortClause\DatePublished( Query::SORT_DESC )
        );

        // Initialize pagination.
        $pager = new Pagerfanta(
            new ContentSearchAdapter( $query, $this->getRepository()->getSearchService() )
        );
        $pager->setMaxPerPage( $this->container->getParameter( 'sillonbol.category.category_list.limit' ) );
        $pager->setCurrentPage( $this->getRequest()->get( 'page', 1 ) );

        return $this->render(
            'SillonbolWebBundle:full:categoria.html.twig',
            array(
                'location' => $location,
                'content' => $content,
                'pagerBlog' => $pager
            ),
            $response
        );
    }

    public function contactAction(Request $request)
    {
        $form = $this->createForm(new ContactType());

        if ( $request->isMethod('POST') ) 
        {
            $form->bind($request);

            if ($form->isValid()) 
            {
                $message = \Swift_Message::newInstance()
                    ->setSubject($form->get('subject')->getData())
                    ->setFrom($form->get('email')->getData())
                    ->setTo('contact@example.com')
                    ->setBody(
                        $this->renderView(
                            'LCWebsiteBundle:Mail:contact.html.twig',
                            array(
                                'ip' => $request->getClientIp(),
                                'name' => $form->get('name')->getData(),
                                'message' => $form->get('message')->getData()
                            )
                        )
                    );

                $this->get('mailer')->send($message);

                $request->getSession()->getFlashBag()->add('success', 'Your email has been sent! Thanks!');

                return $this->redirect($this->generateUrl('contact'));
            }
        }

        return array(
            'form' => $form->createView()
        );
    }
}

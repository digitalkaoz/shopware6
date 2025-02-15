<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Service;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Event\NavigationLoadedEvent;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractNavigationRoute;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Decoratable()
 */
class NavigationLoader implements NavigationLoaderInterface
{
    /**
     * @var TreeItem
     */
    private $treeItem;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AbstractNavigationRoute
     */
    private $navigationRoute;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        AbstractNavigationRoute $navigationRoute
    ) {
        $this->treeItem = new TreeItem(null, []);
        $this->eventDispatcher = $eventDispatcher;
        $this->navigationRoute = $navigationRoute;
    }

    /**
     * {@inheritdoc}
     *
     * @throws CategoryNotFoundException
     */
    public function load(string $activeId, SalesChannelContext $context, string $rootId, int $depth = 2): Tree
    {
        $request = new Request();
        $request->query->set('buildTree', 'false');
        $request->query->set('depth', (string) $depth);

        $criteria = new Criteria();
        $criteria->setTitle('header::navigation');

        $categories = $this->navigationRoute
            ->load($activeId, $rootId, $request, $context, $criteria)
            ->getCategories();

        $navigation = $this->getTree($rootId, $categories, $categories->get($activeId));

        $event = new NavigationLoadedEvent($navigation, $context);

        $this->eventDispatcher->dispatch($event);

        return $event->getNavigation();
    }

    private function getTree(?string $rootId, CategoryCollection $categories, ?CategoryEntity $active): Tree
    {
        $parents = [];
        $items = [];
        foreach ($categories as $category) {
            $item = clone $this->treeItem;
            $item->setCategory($category);

            $parents[$category->getParentId()][$category->getId()] = $item;
            $items[$category->getId()] = $item;
        }

        foreach ($parents as $parentId => $children) {
            if (empty($parentId)) {
                continue;
            }

            $sorted = AfterSort::sort($children);

            $filtered = \array_filter($sorted, static function (TreeItem $filter) {
                return $filter->getCategory()->getActive() && $filter->getCategory()->getVisible();
            });

            if (!isset($items[$parentId])) {
                continue;
            }

            $item = $items[$parentId];
            $item->setChildren($filtered);
        }

        $root = $parents[$rootId] ?? [];

        return new Tree($active, AfterSort::sort($root));
    }
}

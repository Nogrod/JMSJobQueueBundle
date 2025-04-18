<?php

declare(strict_types=1);

namespace JMS\JobQueueBundle\Entity\Listener;

use ArrayIterator;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ManagerRegistry;
use JMS\JobQueueBundle\Entity\Job;

/**
 * Collection for persistent related entities.
 *
 * We do not support all of Doctrine's built-in features.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PersistentRelatedEntitiesCollection implements Collection, Selectable, \Stringable
{
    private ?array $entities = null;

    public function __construct(private readonly ManagerRegistry $registry, private readonly Job $job)
    {
    }

    /**
     * Gets the PHP array representation of this collection.
     *
     * @return array<object> The PHP array representation of this collection.
     */
    public function toArray()
    {
        $this->initialize();

        return $this->entities;
    }

    /**
     * Sets the internal iterator to the first element in the collection and
     * returns this element.
     *
     * @return object|false
     */
    public function first()
    {
        $this->initialize();

        return reset($this->entities);
    }

    /**
     * Sets the internal iterator to the last element in the collection and
     * returns this element.
     *
     * @return object|false
     */
    public function last()
    {
        $this->initialize();

        return end($this->entities);
    }

    /**
     * Gets the current key/index at the current internal iterator position.
     *
     * @return string|integer
     */
    public function key()
    {
        $this->initialize();

        return key($this->entities);
    }

    /**
     * Moves the internal iterator position to the next element.
     *
     * @return object|false
     */
    public function next()
    {
        $this->initialize();

        return next($this->entities);
    }

    /**
     * Gets the element of the collection at the current internal iterator position.
     *
     * @return object|false
     */
    public function current()
    {
        $this->initialize();

        return current($this->entities);
    }

    /**
     * Removes an element with a specific key/index from the collection.
     *
     * @return object|null The removed element or NULL, if no element exists for the given key.
     */
    public function remove(int|string $key)
    {
        throw new \LogicException('remove() is not supported.');
    }

    /**
     * Removes the specified element from the collection, if it is found.
     *
     * @param object $element The element to remove.
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeElement(mixed $element)
    {
        throw new \LogicException('removeElement() is not supported.');
    }

    /**
     * ArrayAccess implementation of offsetExists()
     *
     * @see containsKey()
     */
    public function offsetExists(mixed $offset): bool
    {
        $this->initialize();

        return $this->containsKey($offset);
    }

    /**
     * ArrayAccess implementation of offsetGet()
     *
     * @see get()
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->initialize();

        return $this->get($offset);
    }

    /**
     * ArrayAccess implementation of offsetSet()
     *
     * @see add()
     * @see set()
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Adding new related entities is not supported after initial creation.');
    }

    /**
     * ArrayAccess implementation of offsetUnset()
     *
     * @see remove()
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('unset() is not supported.');
    }

    /**
     * Checks whether the collection contains a specific key/index.
     *
     * @param mixed $key The key to check for.
     * @return boolean TRUE if the given key/index exists, FALSE otherwise.
     */
    public function containsKey(string|int $key)
    {
        $this->initialize();

        return isset($this->entities[$key]);
    }

    /**
     * Checks whether the given element is contained in the collection.
     * Only element values are compared, not keys. The comparison of two elements
     * is strict, that means not only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @return boolean TRUE if the given element is contained in the collection,
     *          FALSE otherwise.
     */
    public function contains(mixed $element)
    {
        $this->initialize();
        return in_array($element, $this->entities, true);
    }

    /**
     * Tests for the existence of an element that satisfies the given predicate.
     *
     * @param Closure $p The predicate.
     * @return boolean TRUE if the predicate is TRUE for at least one element, FALSE otherwise.
     */
    public function exists(Closure $p)
    {
        $this->initialize();

        foreach ($this->entities as $key => $element) {
            if ($p($key, $element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Searches for a given element and, if found, returns the corresponding key/index
     * of that element. The comparison of two elements is strict, that means not
     * only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @param mixed $element The element to search for.
     * @return mixed The key/index of the element or FALSE if the element was not found.
     */
    public function indexOf(mixed $element)
    {
        $this->initialize();

        return array_search($element, $this->entities, true);
    }

    /**
     * Gets the element with the given key/index.
     *
     * @param mixed $key The key.
     * @return mixed The element or NULL, if no element exists for the given key.
     */
    public function get(string|int $key)
    {
        $this->initialize();
        return $this->entities[$key] ?? null;
    }

    /**
     * Gets all keys/indexes of the collection elements.
     *
     * @return array
     */
    public function getKeys()
    {
        $this->initialize();

        return array_keys($this->entities);
    }

    /**
     * Gets all elements.
     *
     * @return array
     */
    public function getValues()
    {
        $this->initialize();

        return array_values($this->entities);
    }

    /**
     * Returns the number of elements in the collection.
     *
     * Implementation of the Countable interface.
     *
     * @return integer The number of elements in the collection.
     */
    public function count(): int
    {
        $this->initialize();

        return count($this->entities);
    }

    /**
     * Adds/sets an element in the collection at the index / with the specified key.
     *
     * When the collection is a Map this is like put(key,value)/add(key,value).
     * When the collection is a List this is like add(position,value).
     *
     * @param mixed $key
     */
    public function set(string|int $key, mixed $value)
    {
        throw new \LogicException('set() is not supported.');
    }

    /**
     * Adds an element to the collection.
     *
     * @return boolean Always TRUE.
     */
    public function add(mixed $value)
    {
        throw new \LogicException('Adding new entities is not supported after creation.');
    }

    /**
     * Checks whether the collection is empty.
     *
     * Note: This is preferable over count() == 0.
     *
     * @return boolean TRUE if the collection is empty, FALSE otherwise.
     */
    public function isEmpty()
    {
        $this->initialize();

        return ! $this->entities;
    }

    /**
     * Gets an iterator for iterating over the elements in the collection.
     *
     * @return ArrayIterator
     */
    public function getIterator(): \Traversable
    {
        $this->initialize();

        return new ArrayIterator($this->entities);
    }

    /**
     * Applies the given function to each element in the collection and returns
     * a new collection with the elements returned by the function.
     *
     * @return Collection
     */
    public function map(Closure $func)
    {
        $this->initialize();

        return new ArrayCollection(array_map($func, $this->entities));
    }

    /**
     * Returns all the elements of this collection that satisfy the predicate p.
     * The order of the elements is preserved.
     *
     * @param Closure $p The predicate used for filtering.
     * @return Collection A collection with the results of the filter operation.
     */
    public function filter(Closure $p)
    {
        $this->initialize();

        return new ArrayCollection(array_filter($this->entities, $p));
    }

    /**
     * Applies the given predicate p to all elements of this collection,
     * returning true, if the predicate yields true for all elements.
     *
     * @param Closure $p The predicate.
     * @return boolean TRUE, if the predicate yields TRUE for all elements, FALSE otherwise.
     */
    public function forAll(Closure $p)
    {
        $this->initialize();

        foreach ($this->entities as $key => $element) {
            if (! $p($key, $element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Partitions this collection in two collections according to a predicate.
     * Keys are preserved in the resulting collections.
     *
     * @param Closure $p The predicate on which to partition.
     * @return array An array with two elements. The first element contains the collection
     *               of elements where the predicate returned TRUE, the second element
     *               contains the collection of elements where the predicate returned FALSE.
     */
    public function partition(Closure $p)
    {
        $this->initialize();
        $coll1 = [];
        $coll2 = [];
        foreach ($this->entities as $key => $element) {
            if ($p($key, $element)) {
                $coll1[$key] = $element;
            } else {
                $coll2[$key] = $element;
            }
        }

        return [new ArrayCollection($coll1), new ArrayCollection($coll2)];
    }

    /**
     * Returns a string representation of this object.
     */
    public function __toString(): string
    {
        return self::class . '@' . spl_object_hash($this);
    }

    /**
     * Clears the collection.
     */
    public function clear()
    {
        throw new \LogicException('clear() is not supported.');
    }

    /**
     * Extract a slice of $length elements starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param int $length
     * @return array
     */
    public function slice(int $offset, int|null $length = null)
    {
        $this->initialize();

        return array_slice($this->entities, $offset, $length, true);
    }

    /**
     * Select all elements from a selectable that match the criteria and
     * return a new collection containing these elements.
     *
     * @return Collection
     */
    public function matching(Criteria $criteria)
    {
        $this->initialize();

        $expr     = $criteria->getWhereExpression();
        $filtered = $this->entities;

        if ($expr !== null) {
            $visitor  = new ClosureExpressionVisitor();
            $filter   = $visitor->dispatch($expr);
            $filtered = array_filter($filtered, $filter);
        }

        if (null !== $orderings = $criteria->getOrderings()) {
            $next = null;
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = ClosureExpressionVisitor::sortByField($field, $ordering == 'DESC' ? -1 : 1, $next);
            }

            usort($filtered, $next);
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset || $length) {
            $filtered = array_slice($filtered, (int)$offset, $length);
        }

        return new ArrayCollection($filtered);
    }

    private function initialize(): void
    {
        if (null !== $this->entities) {
            return;
        }

        $con = $this->registry->getManagerForClass(Job::class)->getConnection();
        $entitiesPerClass = [];
        $count = 0;
        foreach ($con->query("SELECT related_class, related_id FROM jms_job_related_entities WHERE job_id = ".$this->job->getId()) as $data) {
            $count += 1;
            $entitiesPerClass[$data['related_class']][] = json_decode((string) $data['related_id'], true);
        }

        if (0 === $count) {
            $this->entities = [];

            return;
        }

        $entities = [];
        foreach ($entitiesPerClass as $className => $ids) {
            $em = $this->registry->getManagerForClass($className);
            $qb = $em->createQueryBuilder()
                        ->select('e')->from($className, 'e');

            $i = 0;
            foreach ($ids as $id) {
                $expr = null;
                foreach ($id as $k => $v) {
                    if (null === $expr) {
                        $expr = $qb->expr()->eq('e.'.$k, '?'.(++$i));
                    } else {
                        $expr = $qb->expr()->andX($expr, $qb->expr()->eq('e.'.$k, '?'.(++$i)));
                    }

                    $qb->setParameter($i, $v);
                }

                $qb->orWhere($expr);
            }

            $entities = array_merge($entities, $qb->getQuery()->getResult());
        }

        $this->entities = $entities;
    }

    public function findFirst(Closure $p)
    {
        foreach ($this->entities as $value) {
            if ($p($value)) {
                return $value;
            }
        }

        return null;
    }

    public function reduce(Closure $func, mixed $initial = null)
    {
        return array_reduce($this->entities, $func, $initial);
    }
}

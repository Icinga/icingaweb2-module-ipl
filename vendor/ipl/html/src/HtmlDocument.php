<?php

namespace ipl\Html;

use Countable;
use Exception;
use InvalidArgumentException;
use ipl\Html\Contract\Wrappable;

/**
 * HTML document
 *
 * An HTML document is composed of a tree of HTML nodes, i.e. text nodes and HTML elements.
 */
class HtmlDocument implements Countable, Wrappable
{
    /** @var string Content separator */
    protected $contentSeparator = '';

    /** @var bool Whether the document has been assembled */
    protected $hasBeenAssembled = false;

    /** @var Wrappable Wrapper */
    protected $wrapper;

    /** @var ValidHtml[] Content */
    private $content = [];

    /** @var array */
    private $contentIndex = [];

    /**
     * Get the content
     *
     * return ValidHtml[]
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set the content
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = [];
        $this->add($content);

        return $this;
    }

    /**
     * Get the content separator
     *
     * @return string
     */
    public function getSeparator()
    {
        return $this->contentSeparator;
    }

    /**
     * Set the content separator
     *
     * @param string $separator
     *
     * @return $this
     */
    public function setSeparator($separator)
    {
        $this->contentSeparator = $separator;

        return $this;
    }

    /**
     * Get the first {@link BaseHtmlElement} with the given tag
     *
     * @param string $tag
     *
     * @return BaseHtmlElement
     *
     * @throws InvalidArgumentException If no {@link BaseHtmlElement} with the given tag exists
     */
    public function getFirst($tag)
    {
        foreach ($this->content as $c) {
            if ($c instanceof BaseHtmlElement && $c->getTag() === $tag) {
                return $c;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Trying to get first %s, but there is no such',
            $tag
        ));
    }

    /**
     * Add content
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function add($content)
    {
        if (is_iterable($content) && ! $content instanceof ValidHtml) {
            foreach ($content as $c) {
                $this->add($c);
            }
        } elseif ($content !== null) {
            $this->addIndexedContent(Html::wantHtml($content));
        }

        return $this;
    }

    /**
     * Add content from the given document
     *
     * @param HtmlDocument $from
     * @param callable     $callback Optional callback in order to transform the content to add
     *
     * @return $this
     */
    public function addFrom(HtmlDocument $from, $callback = null)
    {
        $from->ensureAssembled();

        $isCallable = is_callable($callback);
        foreach ($from->getContent() as $item) {
            $this->add($isCallable ? $callback($item) : $item);
        }

        return $this;
    }

    /**
     * Prepend content
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function prepend($content)
    {
        if (is_iterable($content) && ! $content instanceof ValidHtml) {
            foreach (array_reverse(is_array($content) ? $content : iterator_to_array($content)) as $c) {
                $this->prepend($c);
            }
        } elseif ($content !== null) {
            $pos = 0;
            $html = Html::wantHtml($content);
            array_unshift($this->content, $html);
            $this->incrementIndexKeys();
            $this->addObjectPosition($html, $pos);
        }

        return $this;
    }

    /**
     * Remove content
     *
     * @param ValidHtml $html
     *
     * @return $this
     */
    public function remove(ValidHtml $html)
    {
        $key = spl_object_hash($html);
        if (array_key_exists($key, $this->contentIndex)) {
            foreach ($this->contentIndex[$key] as $pos) {
                unset($this->content[$pos]);
            }
        }
        $this->content = array_values($this->content);

        $this->reIndexContent();

        return $this;
    }

    /**
     * Ensure that the document has been assembled
     *
     * @return $this
     */
    public function ensureAssembled()
    {
        if (! $this->hasBeenAssembled) {
            $this->hasBeenAssembled = true;
            $this->assemble();
        }

        return $this;
    }

    /**
     * Get whether the document is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->content);
    }

    /**
     * Render the content to HTML but ignore any wrapper
     *
     * @return string
     */
    public function renderUnwrapped()
    {
        $this->ensureAssembled();
        $html = [];

        foreach ($this->content as $element) {
            $html[] = $element->render();
        }

        return implode($this->contentSeparator, $html);
    }

    public function __clone()
    {
        foreach ($this->content as $key => $element) {
            $this->content[$key] = clone $element;
        }

        $this->reIndexContent();
    }

    /**
     * Render content to HTML when treated like a string
     *
     * Calls {@link render()} internally in order to render the text to HTML.
     * Exceptions will be automatically caught and returned as HTML string as well using {@link Error::render()}.
     *
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return Error::render($e);
        }
    }

    /**
     * Assemble the document
     *
     * Override this method in order to provide content in concrete classes.
     */
    protected function assemble()
    {
    }

    /**
     * Render the document to HTML respecting the set wrapper
     *
     * @return string
     */
    protected function renderWrapped()
    {
        // TODO: we don't like this, but have no better solution right now.
        //       However, it works as expected, tests are green
        $wrapper = $this->wrapper;
        $this->wrapper = null;
        $result = $wrapper->renderWrappedDocument($this);
        $this->wrapper = $wrapper;

        return $result;
    }

    /**
     * Render the given document to HTML by treating this document as the wrapper
     *
     * @param HtmlDocument $document
     *
     * @return string
     */
    protected function renderWrappedDocument(HtmlDocument $document)
    {
        $wrapper = clone $this;

        $wrapper->ensureAssembled();

        $key = spl_object_hash($document);

        if (! array_key_exists($key, $wrapper->contentIndex)) {
            $wrapper->add($document);
        }

        return $wrapper->render();
    }

    public function count()
    {
        return count($this->content);
    }

    public function getWrapper()
    {
        return $this->wrapper;
    }

    public function setWrapper(Wrappable $wrapper)
    {
        $this->wrapper = $wrapper;

        return $this;
    }

    public function addWrapper(Wrappable $wrapper)
    {
        if ($this->wrapper === null) {
            $this->setWrapper($wrapper);
        } else {
            $this->wrapper->addWrapper($wrapper);
        }

        return $this;
    }

    public function prependWrapper(Wrappable $wrapper)
    {
        if ($this->wrapper === null) {
            $this->setWrapper($wrapper);
        } else {
            $wrapper->addWrapper($this->wrapper);
            $this->setWrapper($wrapper);
        }

        return $this;
    }

    public function render()
    {
        $this->ensureAssembled();
        if ($this->wrapper === null) {
            return $this->renderUnwrapped();
        } else {
            return $this->renderWrapped();
        }
    }

    private function addIndexedContent(ValidHtml $html)
    {
        $pos = count($this->content);
        $this->content[$pos] = $html;
        $this->addObjectPosition($html, $pos);
    }

    private function addObjectPosition(ValidHtml $html, $pos)
    {
        $key = spl_object_hash($html);
        if (array_key_exists($key, $this->contentIndex)) {
            $this->contentIndex[$key][] = $pos;
        } else {
            $this->contentIndex[$key] = [$pos];
        }
    }

    private function incrementIndexKeys()
    {
        foreach ($this->contentIndex as & $index) {
            foreach ($index as & $pos) {
                $pos++;
            }
        }
    }

    private function reIndexContent()
    {
        $this->contentIndex = [];
        foreach ($this->content as $pos => $html) {
            $this->addObjectPosition($html, $pos);
        }
    }
}

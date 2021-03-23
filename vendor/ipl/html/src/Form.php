<?php

namespace ipl\Html;

use Exception;
use ipl\Html\Contract\FormElement;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\FormElement\FormElements;
use ipl\Stdlib\Messages;
use Psr\Http\Message\ServerRequestInterface;

class Form extends BaseHtmlElement
{
    use FormElements {
        remove as private removeElement;
    }
    use Messages;

    const ON_ELEMENT_REGISTERED = 'elementRegistered';
    const ON_ERROR = 'error';
    const ON_REQUEST = 'request';
    const ON_SUCCESS = 'success';
    const ON_SENT = 'sent';

    /** @var string Form submission URL */
    protected $action;

    /** @var string HTTP method to submit the form with */
    protected $method = 'POST';

    /** @var FormSubmitElement Primary submit button */
    protected $submitButton;

    /** @var FormSubmitElement[] Other elements that may submit the form */
    protected $submitElements = [];

    /** @var bool Whether the form is valid */
    protected $isValid;

    /** @var ServerRequestInterface The server request being processed */
    protected $request;

    /** @var string */
    protected $redirectUrl;

    protected $tag = 'form';

    /**
     * Get the Form submission URL
     *
     * @return string|null
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set the Form submission URL
     *
     * @param string $action
     *
     * @return $this
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get the HTTP method to submit the form with
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Set the HTTP method to submit the form with
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = strtoupper($method);

        return $this;
    }

    /**
     * Get whether the form has a primary submit button
     *
     * @return bool
     */
    public function hasSubmitButton()
    {
        return $this->submitButton !== null;
    }

    /**
     * Get the primary submit button
     *
     * @return FormSubmitElement|null
     */
    public function getSubmitButton()
    {
        return $this->submitButton;
    }

    /**
     * Set the primary submit button
     *
     * @param FormSubmitElement $element
     *
     * @return $this
     */
    public function setSubmitButton(FormSubmitElement $element)
    {
        $this->submitButton = $element;

        return $this;
    }

    /**
     * Get the submit element used to send the form
     *
     * @return FormSubmitElement|null
     */
    public function getPressedSubmitElement()
    {
        foreach ($this->submitElements as $submitElement) {
            if ($submitElement->hasBeenPressed()) {
                return $submitElement;
            }
        }

        return null;
    }

    /**
     * @return ServerRequestInterface|null
     */
    public function getRequest()
    {
        return $this->request;
    }

    public function setRequest($request)
    {
        $this->request = $request;
        $this->emit(Form::ON_REQUEST, [$request]);

        return $this;
    }

    /**
     * Get the url to redirect to on success
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * Set the url to redirect to on success
     *
     * @param string $url
     *
     * @return $this
     */
    public function setRedirectUrl($url)
    {
        $this->redirectUrl = $url;

        return $this;
    }

    /**
     * @param ServerRequestInterface $request
     * @return $this
     */
    public function handleRequest(ServerRequestInterface $request)
    {
        $this->setRequest($request);

        $method = $request->getMethod();

        if ($method !== $this->getMethod()) {
            // Always assemble
            $this->ensureAssembled();

            return $this;
        }

        switch ($method) {
            case 'POST':
                $params = $request->getParsedBody();

                break;
            case 'GET':
                parse_str($request->getUri()->getQuery(), $params);

                break;
            default:
                $params = [];
        }

        $this->populate($params);

        // Assemble after populate in order to conditionally provide form elements
        $this->ensureAssembled();

        if ($this->hasBeenSubmitted()) {
            if ($this->isValid()) {
                try {
                    $this->emit(Form::ON_SENT, [$this]);
                    $this->onSuccess();
                    $this->emitOnce(Form::ON_SUCCESS, [$this]);
                } catch (Exception $e) {
                    $this->addMessage($e);
                    $this->onError();
                    $this->emit(Form::ON_ERROR, [$e, $this]);
                }
            } else {
                $this->onError();
            }
        } else {
            $this->validatePartial();
            $this->emit(Form::ON_SENT, [$this]);
        }

        return $this;
    }

    /**
     * Get whether the form has been sent
     *
     * A form is considered sent if the request's method equals the form's method.
     *
     * @return bool
     */
    public function hasBeenSent()
    {
        if ($this->request === null) {
            return false;
        }

        return $this->request->getMethod() === $this->getMethod();
    }

    /**
     * Get whether the form has been submitted
     *
     * A form is submitted when it has been sent and when the primary submit button, if set, has been pressed.
     * This method calls {@link hasBeenSent()} in order to detect whether the form has been sent.
     *
     * @return bool
     */
    public function hasBeenSubmitted()
    {
        if (! $this->hasBeenSent()) {
            return false;
        }

        if ($this->hasSubmitButton()) {
            return $this->getSubmitButton()->hasBeenPressed();
        }

        return true;
    }

    /**
     * Get whether the form is valid
     *
     * {@link validate()} is called automatically if the form has not been validated before.
     *
     * @return bool
     */
    public function isValid()
    {
        if ($this->isValid === null) {
            $this->validate();
        }

        return $this->isValid;
    }

    /**
     * Validate all elements
     *
     * @return $this
     */
    public function validate()
    {
        $valid = true;
        foreach ($this->elements as $element) {
            if ($element->isRequired() && ! $element->hasValue()) {
                $element->addMessage('This field is required');
                $valid = false;
                continue;
            }
            if (! $element->isValid()) {
                $valid = false;
            }
        }

        $this->isValid = $valid;

        return $this;
    }

    /**
     * Validate all elements that have a value
     *
     * @return $this
     */
    public function validatePartial()
    {
        foreach ($this->getElements() as $element) {
            $element->validate();
        }

        return $this;
    }

    public function remove(ValidHtml $elementOrHtml)
    {
        if ($this->submitButton === $elementOrHtml) {
            $this->submitButton = null;
        }

        $this->removeElement($elementOrHtml);
    }

    protected function onError()
    {
        $errors = Html::tag('ul', ['class' => 'errors']);
        foreach ($this->getMessages() as $message) {
            if ($message instanceof Exception) {
                $message = $message->getMessage();
            }

            $errors->add(Html::tag('li', $message));
        }

        if (! $errors->isEmpty()) {
            $this->prepend($errors);
        }
    }

    protected function onSuccess()
    {
        // $this->redirectOnSuccess();
    }

    protected function onElementRegistered(FormElement $element)
    {
        if ($element instanceof FormSubmitElement) {
            $this->submitElements[$element->getName()] = $element;

            if (! $this->hasSubmitButton()) {
                $this->setSubmitButton($element);
            }
        }
    }

    protected function registerAttributeCallbacks(Attributes $attributes)
    {
        $attributes
            ->registerAttributeCallback('action', [$this, 'getAction'], [$this, 'setAction'])
            ->registerAttributeCallback('method', [$this, 'getMethod'], [$this, 'setMethod']);
    }
}

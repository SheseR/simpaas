<?php declare(strict_types=1);

namespace Levtechdev\Simpaas\Validation;

trait ValidationErrorsTrait
{
    protected array $validationErrors = [];

    /**
     * @param $error
     * @param null  $index
     *
     * @return $this
     */
    protected function addValidationError($error, $index = null): self
    {
        if ($index !== null) {
            $this->validationErrors[$index] = $error;
        } else {
            $this->validationErrors[] = $error;
        }

        return $this;
    }

    /**
     * @param array $errors
     *
     * @return $this
     */
    protected function setValidationErrors(array $errors): self
    {
        $this->validationErrors = $errors;

        return $this;
    }

    /**
     * @param array $errors
     *
     * @return $this
     */
    protected function addValidationErrors(array $errors): self
    {
        $this->validationErrors = array_merge($this->validationErrors, $errors);

        return $this;
    }

    /**
     * @return array
     */
    public function getDataValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * @return array
     */
    public function cleanValidationErrors(): array
    {
        return $this->validationErrors = [];
    }
}

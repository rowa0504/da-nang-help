<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by UpdateCategoryAction when a proposed parent_id would make the
 * category its own ancestor (self-reference or a deeper cycle). The
 * message is resolved via __('admin.categories.invalid_hierarchy') at the
 * throw site, so it is already in the acting Admin's locale by the time it
 * reaches getMessage() — Controllers just forward it like any other
 * business exception message.
 */
class InvalidCategoryHierarchyException extends RuntimeException
{
    //
}

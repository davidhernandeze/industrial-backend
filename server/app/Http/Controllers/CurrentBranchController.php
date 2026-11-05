<?php

namespace App\Http\Controllers;

use App\Filament\Navigation\SidebarNavigation;
use App\Support\CurrentBranch;
use Filament\Resources\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CurrentBranchController extends Controller
{
    public function __invoke(Request $request, CurrentBranch $currentBranch): RedirectResponse
    {
        $request->validate(['branch_id' => ['required', 'integer']]);

        $currentBranch->set($currentBranch->find($request->integer('branch_id')));

        return redirect($this->redirectUrl($request));
    }

    /**
     * Back to where the user was, except on a record of the previous branch: that record
     * does not exist in the new one, so go to the module's list instead.
     */
    private function redirectUrl(Request $request): string
    {
        $previous = url()->previous();

        try {
            $route = Route::getRoutes()->match(Request::create($previous));
        } catch (HttpExceptionInterface) {
            return $previous;
        }

        $page = $route->getControllerClass();

        if (! $route->hasParameter('record') || ! is_subclass_of((string) $page, Page::class)) {
            return $previous;
        }

        $resource = $page::getResource();

        return SidebarNavigation::isBranchScoped($resource) ? $resource::getUrl('index') : $previous;
    }
}

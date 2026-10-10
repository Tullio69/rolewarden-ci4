<?php

declare(strict_types=1);

namespace RoleWardenDemo\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use RoleWardenDemo\Models\DocumentModel;
use RoleWardenDemo\Showcase;

/**
 * The demo's documents. Routes check the permission (documents.update and so
 * on); this controller adds the one rule a permission cannot express: an
 * author edits and submits only their own documents.
 */
class DocumentsController extends Controller
{
    protected $helpers = ['rolewarden', 'form'];

    public function index(): string
    {
        return $this->page('documents/index', ['documents' => model(DocumentModel::class)->listing()]);
    }

    public function create(): string
    {
        return $this->page('documents/form', ['document' => null]);
    }

    public function store(): RedirectResponse
    {
        if (! $this->validateData($this->request->getPost() ?? [], $this->rules())) {
            return redirect()->back()->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        model(DocumentModel::class)->insert([
            'title' => (string) $this->request->getPost('title'),
            'body' => (string) $this->request->getPost('body'),
            'status' => 'draft',
            'owner_id' => (int) auth()->id(),
        ]);

        return redirect()->to(site_url('demo'))->with('rw_success', lang('Demo.created'));
    }

    public function edit(int $id): string
    {
        return $this->page('documents/form', ['document' => $this->own($id)]);
    }

    public function update(int $id): RedirectResponse
    {
        $this->own($id);

        if (! $this->validateData($this->request->getPost() ?? [], $this->rules())) {
            return redirect()->back()->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        // An edited document goes back to draft: what was approved is no longer what it says.
        model(DocumentModel::class)->update($id, [
            'title' => (string) $this->request->getPost('title'),
            'body' => (string) $this->request->getPost('body'),
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return redirect()->to(site_url('demo'))->with('rw_success', lang('Demo.updated'));
    }

    public function submit(int $id): RedirectResponse
    {
        $document = $this->own($id);

        if ($document['status'] !== 'draft') {
            return redirect()->to(site_url('demo'))->with('rw_error', lang('Demo.notDraft'));
        }

        model(DocumentModel::class)->update($id, ['status' => 'submitted']);

        return redirect()->to(site_url('demo'))->with('rw_success', lang('Demo.submitted'));
    }

    public function approve(int $id): RedirectResponse
    {
        $document = $this->find($id);

        if ($document['status'] !== 'submitted') {
            return redirect()->to(site_url('demo'))->with('rw_error', lang('Demo.notSubmitted'));
        }

        model(DocumentModel::class)->update($id, ['status' => 'approved', 'approved_by' => (int) auth()->id(), 'approved_at' => date('Y-m-d H:i:s')]);

        return redirect()->to(site_url('demo'))->with('rw_success', lang('Demo.approved'));
    }

    public function delete(int $id): RedirectResponse
    {
        $this->find($id);
        model(DocumentModel::class)->delete($id);

        return redirect()->to(site_url('demo'))->with('rw_success', lang('Demo.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function find(int $id): array
    {
        $document = model(DocumentModel::class)->find($id);

        if (! is_array($document)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $document;
    }

    /**
     * A document the signed-in user owns; someone else's is "not found", as
     * for a document that does not exist.
     *
     * @return array<string, mixed>
     */
    private function own(int $id): array
    {
        $document = $this->find($id);

        if ((int) $document['owner_id'] !== (int) auth()->id()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $document;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function rules(): array
    {
        return [
            'title' => ['label' => 'Demo.fieldTitle', 'rules' => 'required|max_length[160]'],
            'body' => ['label' => 'Demo.body', 'rules' => 'required|max_length[20000]'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $view, array $data): string
    {
        return view('RoleWardenDemo\Views\layout', [
            'body' => view('RoleWardenDemo\Views\\' . str_replace('/', '\\', $view), $data + ['errors' => session()->getFlashdata('rw_errors') ?? []]),
            'showcase' => Showcase::enabled() ? Showcase::accounts() : [],
        ]);
    }
}

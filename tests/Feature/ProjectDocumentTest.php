<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectDocument;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private User $developer;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::create([
            'name'   => 'Docs Workspace',
            'slug'   => 'docs-workspace',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Owner',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->tenantOwner->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->developer = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Developer Dev',
            'email'     => 'dev@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->developer->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name'         => 'Doc Tracking Project',
            'owner_id'     => $this->tenantOwner->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
        ]);

        ProjectMember::create([
            'tenant_id'    => $this->tenant->id,
            'project_id'   => $this->project->id,
            'user_id'      => $this->developer->id,
            'project_role' => 'Developer',
            'is_active'    => true,
        ]);

        $taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Design Sprint',
            'position'   => 1,
        ]);

        $this->task = Task::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_list_id'    => $taskList->id,
            'task_code'       => 'PRJ-0001-T-001',
            'title'           => 'Architecture Specs',
            'status'          => Task::STATUS_IN_PROGRESS,
            'priority'        => 'High',
        ]);

        $this->withHeaders(['X-Tenant' => $this->tenant->slug]);
    }

    public function test_user_can_upload_project_document(): void
    {
        $file = UploadedFile::fake()->create('architecture-v1.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->tenantOwner)
            ->post(route('projects.documents.store', $this->project), [
                'file'        => $file,
                'category'    => ProjectDocument::CATEGORY_DESIGN,
                'title'       => 'Architecture Blueprint v1',
                'remarks'     => 'Initial system diagram and schema design.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('project_documents', [
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'title'       => 'Architecture Blueprint v1',
            'file_name'   => 'architecture-v1.pdf',
            'category'    => ProjectDocument::CATEGORY_DESIGN,
            'uploaded_by' => $this->tenantOwner->id,
        ]);

        $doc = ProjectDocument::query()->where('file_name', 'architecture-v1.pdf')->firstOrFail();
        Storage::disk('local')->assertExists($doc->file_path);
    }

    public function test_user_can_upload_document_attached_to_task(): void
    {
        $file = UploadedFile::fake()->create('test-results.xlsx', 512, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $response = $this->actingAs($this->developer)
            ->post(route('projects.documents.store', $this->project), [
                'file'            => $file,
                'category'        => ProjectDocument::CATEGORY_TEST_CASE,
                'attachable_type' => 'task',
                'attachable_id'   => $this->task->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('project_documents', [
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'attachable_type' => Task::class,
            'attachable_id'   => $this->task->id,
            'category'        => ProjectDocument::CATEGORY_TEST_CASE,
            'file_name'       => 'test-results.xlsx',
        ]);
    }

    public function test_user_can_download_document(): void
    {
        $file = UploadedFile::fake()->create('sow.pdf', 200, 'application/pdf');

        $path = $file->storeAs('tenants/' . $this->tenant->id . '/projects/' . $this->project->id . '/documents', 'sow_stored.pdf', 'local');

        $document = ProjectDocument::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'title'       => 'Contract SOW',
            'file_name'   => 'sow.pdf',
            'file_path'   => $path,
            'file_size'   => 200 * 1024,
            'mime_type'   => 'application/pdf',
            'category'    => ProjectDocument::CATEGORY_REQUIREMENT,
            'uploaded_by' => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->developer)
            ->get(route('projects.documents.download', [$this->project, $document]));

        $response->assertOk();
    }

    public function test_user_can_preview_document(): void
    {
        $file = UploadedFile::fake()->create('preview.pdf', 150, 'application/pdf');
        $path = $file->storeAs('tenants/' . $this->tenant->id . '/projects/' . $this->project->id . '/documents', 'preview.pdf', 'local');

        $document = ProjectDocument::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'title'       => 'Preview Document',
            'file_name'   => 'preview.pdf',
            'file_path'   => $path,
            'file_size'   => 150 * 1024,
            'mime_type'   => 'application/pdf',
            'category'    => ProjectDocument::CATEGORY_REQUIREMENT,
            'uploaded_by' => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->developer)
            ->get(route('projects.documents.preview', [$this->project, $document]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline; filename="preview.pdf"');
    }

    public function test_user_can_delete_document(): void
    {
        $file = UploadedFile::fake()->create('temp.txt', 10, 'text/plain');
        $path = $file->storeAs('tenants/' . $this->tenant->id . '/projects/' . $this->project->id . '/documents', 'temp.txt', 'local');

        $document = ProjectDocument::create([
            'tenant_id'   => $this->tenant->id,
            'project_id'  => $this->project->id,
            'title'       => 'Temp Notes',
            'file_name'   => 'temp.txt',
            'file_path'   => $path,
            'file_size'   => 10,
            'mime_type'   => 'text/plain',
            'category'    => ProjectDocument::CATEGORY_ATTACHMENT,
            'uploaded_by' => $this->developer->id,
        ]);

        $response = $this->actingAs($this->developer)
            ->delete(route('projects.documents.destroy', [$this->project, $document]));

        $response->assertRedirect();
        $this->assertSoftDeleted('project_documents', ['id' => $document->id]);
    }

    public function test_document_validation_rejects_unsupported_mimes(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($this->tenantOwner)
            ->post(route('projects.documents.store', $this->project), [
                'file'     => $file,
                'category' => ProjectDocument::CATEGORY_ATTACHMENT,
            ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_documents_upload_permission(): void
    {
        $readOnlyRole = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();
        $restrictedUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Restricted Doc User',
            'email'     => 'restricted_doc@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $restrictedUser->id,
            'role_id'   => $readOnlyRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $file = UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf');

        $response = $this->actingAs($restrictedUser)
            ->post(route('projects.documents.store', $this->project), [
                'file'     => $file,
                'category' => ProjectDocument::CATEGORY_REQUIREMENT,
            ]);
        $response->assertForbidden();

        $uploaderRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Uploader Role',
            'slug'      => 'uploader_role',
        ]);
        $uploadPerm = Permission::query()->where('name', 'projects.documents.upload')->firstOrFail();
        RolePermission::create([
            'role_id'       => $uploaderRole->id,
            'permission_id' => $uploadPerm->id,
            'scope'         => RolePermission::SCOPE_TENANT,
        ]);
        UserRole::create([
            'user_id'   => $restrictedUser->id,
            'role_id'   => $uploaderRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $successResponse = $this->actingAs($restrictedUser)
            ->post(route('projects.documents.store', $this->project), [
                'file'     => $file,
                'category' => ProjectDocument::CATEGORY_REQUIREMENT,
            ]);
        $successResponse->assertRedirect();
    }

    public function test_milestone_document_relationship(): void
    {
        $milestone = Milestone::create([
            'tenant_id'             => $this->tenant->id,
            'project_id'            => $this->project->id,
            'name'                  => 'Alpha Release',
            'status'                => Milestone::STATUS_ACTIVE,
            'completion_percentage' => 50,
        ]);

        $file = UploadedFile::fake()->create('milestone-charter.pdf', 300, 'application/pdf');
        $path = $file->storeAs('tenants/' . $this->tenant->id . '/projects/' . $this->project->id . '/documents', 'charter.pdf', 'local');

        $doc = ProjectDocument::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'attachable_type' => Milestone::class,
            'attachable_id'   => $milestone->id,
            'title'           => 'Milestone Charter',
            'file_name'       => 'milestone-charter.pdf',
            'file_path'       => $path,
            'file_size'       => 300 * 1024,
            'mime_type'       => 'application/pdf',
            'category'        => ProjectDocument::CATEGORY_REQUIREMENT,
            'uploaded_by'     => $this->tenantOwner->id,
        ]);

        $this->assertTrue($milestone->documents()->exists());
        $this->assertEquals(1, $milestone->documents->count());
        $this->assertEquals('Milestone Charter', $milestone->documents->first()->title);
    }

    public function test_document_activity_event_naming(): void
    {
        $file = UploadedFile::fake()->create('release-notes.pdf', 250, 'application/pdf');

        $this->actingAs($this->tenantOwner)
            ->post(route('projects.documents.store', $this->project), [
                'file'     => $file,
                'title'    => 'Release Notes v1',
                'category' => ProjectDocument::CATEGORY_ATTACHMENT,
            ]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.document_uploaded',
        ]);

        $doc = ProjectDocument::query()->where('title', 'Release Notes v1')->firstOrFail();

        $this->actingAs($this->tenantOwner)
            ->delete(route('projects.documents.destroy', [$this->project, $doc]));

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'project.document_deleted',
        ]);
    }
}

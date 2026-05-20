<?php
/**
 * FILE: /app/controllers/api/SchoolController.php
 * PURPOSE: JSON API endpoint for school typeahead search.
 *          Called via AJAX from the NSRP education step.
 */
class SchoolController extends BaseController
{
    private SchoolService $schoolService;

    public function __construct()
    {
        parent::__construct();
        $this->schoolService = new SchoolService($this->db);
    }

    /** GET /api/schools/search?q=... */
    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');

        if (mb_strlen($query) < 2) {
            $this->json([]);
        }

        $results = $this->schoolService->search($query);
        $this->json($results);
    }

    /** GET /api/schools/{id} */
    public function find(): void
    {
        $id     = (int) ($_GET['id'] ?? 0);
        $school = $id ? $this->schoolService->findById($id) : null;
        $this->json($school ? [$school] : []);
    }

    /** GET /api/schools/types */
    public function types(): void
    {
        $this->json($this->schoolService->getSchoolTypes());
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}

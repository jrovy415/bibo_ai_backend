<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest as ModelRequest;
use App\Models\Student;
use App\Repository\Student\StudentRepositoryInterface;

class StudentController extends Controller
{
    private $modelRepository;

    public function __construct(StudentRepositoryInterface $modelRepository)
    {
        $this->modelRepository = $modelRepository;
    }

    public function index()
    {
        return $this->modelRepository->getList();
    }

    public function store(ModelRequest $request)
    {
        return $this->modelRepository->create($request->validated());
    }

    public function show(string $id)
    {
        return $this->modelRepository->find($id);
    }

    public function update(ModelRequest $request, string $id)
    {
        return $this->modelRepository->update($request->validated(), $id);
    }

    public function destroy(string $id)
    {
        return $this->modelRepository->delete($id);
    }

    public function login(ModelRequest $request)
    {
        return $this->modelRepository->login($request->validated());
    }

    public function logout()
    {
        return $this->modelRepository->logout();
    }

    public function getDifficulty(Student $student)
    {
        $currentDifficulty = $student->currentDifficulty?->difficulty ?? 'Introduction';

        return response()->json([
            'status' => 'success',
            'data' => [
                'student_id' => $student->id,
                'difficulty' => $currentDifficulty,
            ],
        ]);
    }
}

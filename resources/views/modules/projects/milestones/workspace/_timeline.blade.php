@include('modules.projects._timeline', [
    'project'             => $project,
    'milestoneId'         => $milestone->id,
    'milestones'          => collect([$milestone]),
    'selectedMilestoneId' => $milestone->id,
])

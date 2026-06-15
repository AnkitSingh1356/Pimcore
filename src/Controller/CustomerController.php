<?php

namespace App\Controller;

use App\Form\CustomerType;
use Carbon\Carbon;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Customer;
use Pimcore\Model\Element\Service;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CustomerController extends AbstractController
{
    public function __construct(
        private readonly \App\Service\CustomerImportService $customerImportService,
    ) {}

    #[Route('/customers', name: 'customer_list')]
    public function list(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = (int) $request->query->get('limit', 10);
        $limit = in_array($limit, [5, 10, 20, 50], true) ? $limit : 10;

        $listing = new Customer\Listing();
        $listing->setOrderKey('id');
        $listing->setOrder('DESC');
        $listing->setLimit($limit);
        $listing->setOffset(($page - 1) * $limit);

        $importResult = null;
        if ($request->isMethod('POST')) {
            
            $file = $request->files->get('customer_csv');
            if ($file) {
                
                $tmpDir = sys_get_temp_dir();
                $tmpFileName = 'customer_import_' . uniqid('', true) . '.csv';
                $tmpPath = $tmpDir . DIRECTORY_SEPARATOR . $tmpFileName;
                $file->move($tmpDir, $tmpFileName);

                $importResult = $this->customerImportService->importFromFile($tmpPath, 'web');

                
                @unlink($tmpPath);

            }
        }

        $total = 0;
        try {
            
            $total = (int) $listing->getTotalCount();
        } catch (\Throwable) {
            
            $total = count($listing->load());
        }

        $pages = $limit > 0 ? (int) ceil($total / $limit) : 1;
        $pages = max(1, $pages);

        
        $range = [];
        $lastAdded = -1;
        $delta = 2;
        for ($i = 1; $i <= $pages; $i++) {
            if ($i <= 2 || $i >= $pages - 1 || ($i >= $page - $delta && $i <= $page + $delta)) {
                if ($lastAdded !== -1 && $i > $lastAdded + 1) {
                    $range[] = null;
                }
                $range[] = $i;
                $lastAdded = $i;
            }
        }

        $pagination = [
            'page'      => $page,
            'limit'     => $limit,
            'total'     => $total,
            'pages'     => $pages,
            'has_prev'  => $page > 1,
            'has_next'  => $page < $pages,
            'prev_page' => max(1, $page - 1),
            'next_page' => min($pages, $page + 1),
            'range'     => $range,
        ];

        return $this->render('customer/list.html.twig', [
            'customers' => $listing,
            'import_result' => $importResult,
            'pagination' => $pagination,
        ]);

    }

    #[Route(
        '/customers/{id}',
        name: 'customer_detail',
        requirements: ['id' => '\d+']
    )]
    public function detail(int $id): Response
    {
        $customer = Customer::getById($id);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException('Customer not found');
        }

        return $this->render('customer/detail.html.twig', [
            'customerId' => $id
        ]);
    }

    #[Route(
        '/api/customers/{id}',
        name: 'api_customer_detail',
        requirements: ['id' => '\d+']
    )]
    public function apiDetail(int $id): JsonResponse
    {
        $customer = Customer::getById($id);

        if (!$customer instanceof Customer) {
            return $this->json(['error' => 'Customer not found'], 404);
        }

        $dateOfBirth = $customer->getDateOfBirth();

        return $this->json([
            'id'              => $customer->getId(),
            'firstName'       => $customer->getFirstName(),
            'lastName'        => $customer->getLastName(),
            'email'           => $customer->getEmail(),
            'phone'           => $customer->getPhone(),
            'dateOfBirth'     => $dateOfBirth ? $dateOfBirth->format('d M Y') : null,
            'gender'          => $customer->getGender(),
            'address'         => $customer->getAddress(),
            'status'          => $customer->getStatus(),
            'city'            => $customer->getCity(),
            'country'         => $customer->getCountry(),
            'channel'         => $customer->getChannel(),
            'customerType'    => $customer->getCustomerType(),
            'preferredSport'  => $customer->getPreferredSport(),
            'newsletterOptin' => $customer->getNewsletterOptin(),
        ]);
    }

    #[Route('/customers/create', name: 'customer_create')]
    public function create(Request $request): Response
    {
        $form = $this->createForm(CustomerType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            $parent = DataObject::getByPath('/customers');

            if (!$parent) {
                throw new \RuntimeException(
                    'Customers folder "/customers" not found'
                );
            }

            try {
                $customer = new Customer();

                $customer->setParent($parent);

                $key = Service::getValidKey(
                    $data['firstName'] . '-' . $data['lastName'],
                    'object'
                );

                $key .= '-' . time();

                $customer->setKey($key);

                $customer->setFirstName($data['firstName']);
                $customer->setLastName($data['lastName']);
                $customer->setEmail($data['email']);
                $customer->setPhone($data['phone']);

                if (!empty($data['dateOfBirth'])) {
                    $customer->setDateOfBirth(
                        Carbon::instance($data['dateOfBirth'])
                    );
                }

                $customer->setGender($data['gender']);
                $customer->setAddress($data['address']);
                $customer->setStatus($data['status']);

                $customer->setPublished(true);

                $customer->save();

                $this->addFlash(
                    'success',
                    'Customer created successfully.'
                );

                return $this->redirectToRoute('customer_list');

            } catch (\Throwable $e) {

                $this->addFlash(
                    'error',
                    'Failed to create customer: ' . $e->getMessage()
                );
            }
        }

        return $this->render('customer/create.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route(
        '/customers/{id}/edit',
        name: 'customer_edit',
        requirements: ['id' => '\d+']
    )]
    public function edit(
        int $id,
        Request $request
    ): Response {
        $customer = Customer::getById($id);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException(
                'Customer not found'
            );
        }

        $form = $this->createForm(
            CustomerType::class,
            [
                'firstName' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'dateOfBirth' => $customer->getDateOfBirth(),
                'gender' => $customer->getGender(),
                'address' => $customer->getAddress(),
                'status' => $customer->getStatus(),
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            $customer->setFirstName($data['firstName']);
            $customer->setLastName($data['lastName']);
            $customer->setEmail($data['email']);
            $customer->setPhone($data['phone']);
            $customer->setGender($data['gender']);
            $customer->setAddress($data['address']);
            $customer->setStatus($data['status']);

            if (!empty($data['dateOfBirth'])) {
                $customer->setDateOfBirth(
                    Carbon::instance($data['dateOfBirth'])
                );
            }

            $customer->save();

            $this->addFlash(
                'success',
                'Customer updated successfully.'
            );

            return $this->redirectToRoute(
                'customer_detail',
                ['id' => $customer->getId()]
            );
        }

        return $this->render('customer/edit.html.twig', [
            'form' => $form->createView(),
            'customer' => $customer
        ]);
    }

    #[Route(
        '/customers/{id}/delete',
        name: 'customer_delete',
        requirements: ['id' => '\d+']
    )]
    public function delete(int $id): Response
    {
        $customer = Customer::getById($id);

        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException(
                'Customer not found'
            );
        }

        try {
            $customer->delete();

            $this->addFlash(
                'success',
                'Customer deleted successfully.'
            );

        } catch (\Throwable $e) {

            $this->addFlash(
                'error',
                'Failed to delete customer: ' . $e->getMessage()
            );
        }

        return $this->redirectToRoute('customer_list');
    }
}

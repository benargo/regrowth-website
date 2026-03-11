import { usePage, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";
import PlannedAbsenceRow from "@/Components/PlannedAbsenceRow";
import usePermission from "@/Hooks/Permissions";

export default function Index() {
    const { auth, roles, planned_absences } = usePage().props;
    const user = auth.user;

    return (
        <Master title="My Account">
            {/* TODO: bg-arcatraz is a temporary header image */}
            <SharedHeader title="My Account" backgroundClass="bg-arcatraz" />

            <div className="py-8 text-white">
                <div className="container mx-auto px-4">
                    {/* User profile section */}
                    <div className="mb-8 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                        <img src={user.avatar} alt={user.display_name} className="h-20 w-20 rounded-full" />
                        <div>
                            <h1 className="text-2xl font-bold text-white">{user.display_name}</h1>
                            <p className="text-sm text-gray-400">@{user.username}</p>
                            {roles.length > 0 && (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {roles.map((role) => (
                                        <Pill
                                            key={role.id}
                                            bgColor={`bg-discord-${role.name.toLowerCase().replace(/\s+/g, "")}`}
                                            textColor="text-white"
                                        >
                                            {role.name}
                                        </Pill>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Planned absences section */}
                    <div>
                        <header className="mb-4 flex flex-col items-center justify-between md:flex-row">
                            <h2 className="text-lg font-semibold text-amber-400">Planned Absences</h2>
                            {usePermission("create-planned-absences") && (
                                <Link
                                    href={route("raids.absences.create")}
                                    className="mt-3 inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 md:mt-0"
                                >
                                    <Icon icon="plus" style="solid" className="mr-1.5 h-4" />
                                    Add Absence
                                </Link>
                            )}
                        </header>

                        {planned_absences.data.length === 0 ? (
                            <div className="py-8 text-center text-gray-400">
                                <Icon icon="calendar-times" style="solid" className="mb-3 text-3xl" />
                                <p>You haven&rsquo;t created any planned absences.</p>
                            </div>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {planned_absences.data.map((absence) => (
                                    <PlannedAbsenceRow key={absence.id} absence={absence} showCreatedBy />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </Master>
    );
}
